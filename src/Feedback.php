<?php

declare (strict_types=1);
namespace Zxcvbn_Php;

use Zxcvbn_Php\Matchers\Match_Interface;
/**
 * Feedback - gives some user guidance based on the strength
 * of a password
 *
 * @see zxcvbn/src/feedback.coffee
 */
class Feedback
{
    /**
     * @param MatchInterface[] $sequence
     */
    public function get_feedback(int $score, array $sequence): array
    {
        // starting feedback
        if (count($sequence) === 0) {
            return ['warning' => '', 'suggestions' => ['Use a few words, avoid common phrases', 'No need for symbols, digits, or uppercase letters']];
        }
        // no feedback if score is good or great.
        if ($score > 2) {
            return ['warning' => '', 'suggestions' => []];
        }
        // tie feedback to the longest match for longer sequences
        $longest_match = $sequence[0];
        foreach (array_slice($sequence, 1) as $match) {
            if (mb_strlen($match->token) > mb_strlen($longest_match->token)) {
                $longest_match = $match;
            }
        }
        $feedback = $longest_match->get_feedback(count($sequence) === 1);
        $extra_feedback = 'Add another word or two. Uncommon words are better.';
        array_unshift($feedback['suggestions'], $extra_feedback);
        return $feedback;
    }
}