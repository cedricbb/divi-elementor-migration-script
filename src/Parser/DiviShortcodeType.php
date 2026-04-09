<?php

declare(strict_types=1);

namespace DiviToElementor\Parser;

enum DiviShortcodeType: string
{
    // Structural containers
    case Section         = 'et_pb_section';
    case Row             = 'et_pb_row';
    case RowInner        = 'et_pb_row_inner';
    case Column          = 'et_pb_column';
    case ColumnInner     = 'et_pb_column_inner';

    // Supported content modules (mapping Elementor defined)
    case Text            = 'et_pb_text';
    case Image           = 'et_pb_image';
    case Button          = 'et_pb_button';
    case CallToAction    = 'et_pb_cta';
    case Video           = 'et_pb_video';
    case Code            = 'et_pb_code';
    case Divider         = 'et_pb_divider';
    case Blurb           = 'et_pb_blurb';
    case Tabs            = 'et_pb_tabs';
    case Tab             = 'et_pb_tab';
    case Toggle          = 'et_pb_toggle';
    case Accordion       = 'et_pb_accordion';
    case Slider          = 'et_pb_slider';
    case Slide           = 'et_pb_slide';
    case Gallery         = 'et_pb_gallery';

    // Unsupported modules (no Elementor mapping)
    case FullwidthSlider = 'et_pb_fullwidth_slider';
    case FullwidthHeader = 'et_pb_fullwidth_header';
    case Portfolio       = 'et_pb_portfolio';
    case Shop            = 'et_pb_shop';
    case CountdownTimer  = 'et_pb_countdown_timer';
    case PricingTable    = 'et_pb_pricing_table';

    // Fallback for any et_pb_* tag not in this enum
    case Unknown         = 'unknown';

    /**
     * Returns the type corresponding to the shortcode tag.
     * Returns self::Unknown if the tag is not recognised — never null.
     */
    public static function fromTag(string $tag): self
    {
        return self::tryFrom($tag) ?? self::Unknown;
    }

    /**
     * Indicates whether this type has a defined Elementor mapping.
     */
    public function isSupported(): bool
    {
        return !in_array($this, [
            self::FullwidthSlider,
            self::FullwidthHeader,
            self::Portfolio,
            self::Shop,
            self::CountdownTimer,
            self::PricingTable,
            self::Unknown,
        ], true);
    }
}
