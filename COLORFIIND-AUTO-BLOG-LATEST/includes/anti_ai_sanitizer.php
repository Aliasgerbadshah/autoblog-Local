<?php
/**
 * AutoBlog SaaS - Anti-AI Sanitizer
 * Replaces AI-sounding phrases with natural human language
 */

class AntiAiSanitizer {
    private static $replacements = [
        "/\bIn today's digital landscape\b/i" => "In the current market",
        "/\bIn today's world\b/i" => "Right now",
        "/\bIn conclusion\b/i" => "The practical result is",
        "/\bTo summarize\b/i" => "In short",
        "/\bIt is important to note\b/i" => "One point matters",
        "/\bIt's worth noting\b/i" => "A useful detail is",
        "/\bNeedless to say\b/i" => "The point is",
        "/\bWithout a doubt\b/i" => "Clearly",
        "/\bAt the end of the day\b/i" => "In practice",
        "/\bWhen it comes to\b/i" => "For",
        "/\bLet's dive in\b/i" => "Here is the practical breakdown",
        "/\bDive into\b/i" => "Examine",
        "/\bDelve into\b/i" => "Examine",
        "/\bdelve\b/i" => "examine",
        "/\bfurthermore\b/i" => "also",
        "/\bmoreover\b/i" => "also",
        "/\badditionally\b/i" => "also",
        "/\bconsequently\b/i" => "so",
        "/\btherefore\b/i" => "so",
        "/\bnotably\b/i" => "clearly",
        "/\bsignificantly\b/i" => "clearly",
        "/\bultimately\b/i" => "in the end",
        "/\bessentially\b/i" => "basically",
        "/\brobust\b/i" => "solid",
        "/\bcomprehensive\b/i" => "detailed",
        "/\binnovative\b/i" => "new",
        "/\bcutting-edge\b/i" => "modern",
        "/\btransformative\b/i" => "major",
        "/\brevolutionary\b/i" => "new",
        "/\bdynamic\b/i" => "active",
        "/\bversatile\b/i" => "flexible",
        "/\bholistic\b/i" => "whole",
        "/\boptimal\b/i" => "best-fit",
        "/\binvaluable\b/i" => "useful",
        "/\bremarkable\b/i" => "strong",
        "/\bsubstantial\b/i" => "large",
        "/\bcrucial\b/i" => "important",
        "/\bpivotal\b/i" => "central",
        "/\bextensive\b/i" => "broad",
        "/\bdiverse\b/i" => "varied",
        "/\bleverage\b/i" => "use",
        "/\bfacilitate\b/i" => "help",
        "/\boptimize\b/i" => "improve",
        "/\benhance\b/i" => "improve",
        "/\bstreamline\b/i" => "simplify",
        "/\butilize\b/i" => "use",
        "/\bimplement\b/i" => "apply",
        "/\bempower\b/i" => "help",
        "/\bfoster\b/i" => "support",
        "/\bnavigate\b/i" => "handle",
        "/\bunlock\b/i" => "open",
        "/\bmaximize\b/i" => "increase",
        "/\bminimize\b/i" => "reduce",
        "/\belevate\b/i" => "raise",
        "/\bunderscore\b/i" => "show",
        "/\billustrate\b/i" => "show",
        "/\bencompass\b/i" => "cover",
        "/\balign\b/i" => "match",
        "/\bintegrate\b/i" => "connect",
        "/\bprioritize\b/i" => "rank first"
    ];

    public static function sanitizeText($text) {
        $sanitized = $text;
        foreach (self::$replacements as $pattern => $replacement) {
            $sanitized = preg_replace($pattern, $replacement, $sanitized);
        }
        return $sanitized;
    }
}
