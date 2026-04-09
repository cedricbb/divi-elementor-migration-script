<?php

declare(strict_types=1);

namespace DiviToElementor\Parser;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DiviParser
{
    private const MAX_CONTENT_LENGTH = 500_000;
    private const MAX_DEPTH          = 10;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Public entry point. Fetches post_content from WordPress and returns the parsed node tree.
     *
     * @param int $post_id WordPress post ID (> 0, validated upstream).
     * @return array<int,DiviNode> Indexed array of root nodes. Empty if no Divi shortcodes found.
     */
    public function parse(int $post_id): array
    {
        $postContent = get_post_field('post_content', $post_id, 'raw');

        if ($postContent === false) {
            $this->log(sprintf(
                'warning: DiviParser::parse() — post_content introuvable pour post_id=%d',
                $post_id
            ));
            return [];
        }

        if ($postContent === '') {
            $this->log(sprintf(
                'warning: DiviParser::parse() — post_content vide pour post_id=%d',
                $post_id
            ));
            return [];
        }

        $content = $this->decodeIfEncoded($postContent);

        // ReDoS mitigation: truncate before passing to regex-based parser
        if (strlen($content) > self::MAX_CONTENT_LENGTH) {
            $this->log(sprintf(
                'warning: DiviParser::parse() — contenu trop volumineux (%d chars), tronqué à %d',
                strlen($content),
                self::MAX_CONTENT_LENGTH
            ));
            $content = substr($content, 0, self::MAX_CONTENT_LENGTH);
        }

        $nodes = $this->parseShortcodes($content);

        if (empty($nodes)) {
            $this->log(sprintf(
                'warning: DiviParser::parse() — aucun shortcode Divi pour post_id=%d',
                $post_id
            ));
            return [];
        }

        return $nodes;
    }

    /**
     * Recursive shortcode parser. Builds the DiviNode tree for the given content string.
     *
     * @param string $content Content to parse (already decoded).
     * @param int    $depth   Current recursion depth (internal — defaults to 0).
     * @return array<int,DiviNode>
     */
    public function parseShortcodes(string $content, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            $this->log(sprintf(
                'warning: DiviParser::parseShortcodes() — profondeur max atteinte (depth=%d)',
                self::MAX_DEPTH
            ));
            return [];
        }

        $nodes  = [];
        $pos    = 0;
        $length = strlen($content);

        while ($pos < $length) {
            $tagStart = strpos($content, '[et_pb_', $pos);
            if ($tagStart === false) {
                break;
            }

            // Parse the opening tag: [et_pb_tagname optional_attributes]
            if (!preg_match(
                '/\G\[et_pb_([a-z_]+)((?:\s[^\]]*)?)\]/s',
                $content,
                $m,
                0,
                $tagStart
            )) {
                $pos = $tagStart + 1;
                continue;
            }

            $tag      = 'et_pb_' . $m[1];
            $attrsStr = trim($m[2]);
            $openEnd  = $tagStart + strlen($m[0]);

            // Find the matching closing tag accounting for same-tag nesting
            [$closeStart, $closeEnd] = $this->findMatchingClose($content, $tag, $openEnd);

            if ($closeStart === false) {
                // Malformed: no closing tag found
                $raw          = substr($content, $tagStart, $openEnd - $tagStart);
                $type         = DiviShortcodeType::fromTag($tag);
                $parsedAttrs  = $this->parseAttributes($attrsStr);
                $nodes[]      = new DiviNode($type, $parsedAttrs, [], $raw, 'malformed');
                $this->log(sprintf(
                    'warning: DiviParser::parseShortcodes() — shortcode non fermé: %s',
                    $tag
                ));
                $pos = $openEnd;
                continue;
            }

            $innerContent = substr($content, $openEnd, $closeStart - $openEnd);
            $raw          = substr($content, $tagStart, $closeEnd - $tagStart);
            $type         = DiviShortcodeType::fromTag($tag);
            $parsedAttrs  = $this->parseAttributes($attrsStr);
            $children     = $this->parseShortcodes($innerContent, $depth + 1);
            $status       = $type->isSupported() ? 'supported' : 'unsupported';

            $nodes[] = new DiviNode($type, $parsedAttrs, $children, $raw, $status);
            $pos     = $closeEnd;
        }

        return $nodes;
    }

    /**
     * Detects whether $content is base64-encoded Divi content and decodes it.
     * Returns the original string unchanged if not base64 or decoding fails.
     *
     * Detection algorithm:
     *   1. Empty string → return ''
     *   2. Matches base64 charset regex AND length ≥ 16
     *      AND base64_decode(strict:true) !== false
     *      AND decoded string contains '[et_pb_'
     *      → return decoded string
     *   3. Otherwise → return $content as-is
     */
    public function decodeIfEncoded(string $content): string
    {
        if ($content === '') {
            return '';
        }

        if (
            strlen($content) >= 16
            && (bool) preg_match('/^[A-Za-z0-9+\/\r\n]+=*$/', $content)
        ) {
            $decoded = base64_decode($content, true);
            if ($decoded !== false && str_contains($decoded, '[et_pb_')) {
                return $decoded;
            }
        }

        return $content;
    }

    /**
     * Finds the closing tag [/tag] that matches the opening tag already consumed,
     * correctly handling same-tag nesting (e.g. [et_pb_tab]…[/et_pb_tab]).
     *
     * @param string $content  Full content string.
     * @param string $tag      Full tag name, e.g. 'et_pb_section'.
     * @param int    $startPos Position in $content immediately after the opening tag.
     * @return array{int|false, int|false} [closeStart, closeEnd] or [false, false] if not found.
     */
    private function findMatchingClose(string $content, string $tag, int $startPos): array
    {
        $closeTag    = '[/' . $tag . ']';
        $closeLen    = strlen($closeTag);
        $openPattern = '/\[' . preg_quote($tag, '/') . '(?:\s[^\]]*)?]/';
        $depth       = 0;
        $pos         = $startPos;

        while ($pos < strlen($content)) {
            $nextClose = strpos($content, $closeTag, $pos);

            // Search for next same-type opening tag from current position
            $nextOpen     = false;
            $nextOpenLen  = 0;
            if (preg_match($openPattern, $content, $om, PREG_OFFSET_CAPTURE, $pos)) {
                $nextOpen    = $om[0][1];
                $nextOpenLen = strlen($om[0][0]);
            }

            if ($nextClose === false) {
                return [false, false];
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                // Nested same-type tag found before close
                $depth++;
                $pos = $nextOpen + $nextOpenLen;
            } else {
                if ($depth === 0) {
                    return [$nextClose, $nextClose + $closeLen];
                }
                $depth--;
                $pos = $nextClose + $closeLen;
            }
        }

        return [false, false];
    }

    /**
     * Parses a shortcode attribute string into a key/value array.
     * Delegates to WordPress shortcode_parse_atts() when available; falls back to a regex parser.
     *
     * @param string $attrsStr Raw attribute string from inside the opening tag.
     * @return array<string,string>
     */
    private function parseAttributes(string $attrsStr): array
    {
        if ($attrsStr === '') {
            return [];
        }

        if (function_exists('shortcode_parse_atts')) {
            $result = shortcode_parse_atts($attrsStr);
            return is_array($result) ? $result : [];
        }

        // Fallback: handle key="value" and key='value' pairs
        $result = [];
        preg_match_all(
            '/(\w[\w-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/',
            $attrsStr,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $key         = $match[1];
            $value       = ($match[2] !== '') ? $match[2] : $match[3];
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Writes a warning message via the injected logger or error_log() fallback.
     */
    private function log(string $message): void
    {
        $this->logger->warning('[DiviParser] ' . $message);
        error_log('[DiviParser] ' . $message);
    }
}
