# Architecture: zxcvbn-php

## Purpose

A PHP port of the [zxcvbn](https://github.com/dropbox/zxcvbn) password strength estimator by Dropbox. Evaluates password strength by modeling attacker behaviour rather than using simplistic character-class rules.

## Directory Structure

```
src/
  Zxcvbn.php                  - Main entry point
  Matcher.php                 - Aggregates all matchers, runs omnimatch
  Scorer.php                  - Dynamic-programming minimum-guesses scorer
  Time_Estimator.php          - Converts guesses to human-readable crack times
  Feedback.php                - Generates user-facing suggestions
  Matchers/
    Match_Interface.php       - Contract all matchers must satisfy
    Base_Match.php            - Abstract base with shared helpers (find_all, binom)
    Dictionary_Match.php      - Checks against frequency-ranked word lists
    Reverse_Dictionary_Match  - Checks reversed tokens against dictionaries
    L33t_Match.php            - Detects leet-speak substitution variants
    Repeat_Match.php          - Detects repeated character runs
    Sequence_Match.php        - Detects keyboard/alphabetic sequences
    Spatial_Match.php         - Detects keyboard adjacency patterns
    Date_Match.php            - Detects date patterns
    Year_Match.php            - Detects year patterns
    Bruteforce.php            - Fallback match for unrecognised substrings
  Math/
    Binomial.php              - Facade for binomial coefficient calculation
    Binomial_Provider.php     - Interface for binomial providers
    Impl/                     - Multiple implementations (Float64, Int64, GMP)
test/                         - PHPUnit tests mirroring src/ structure
data/                         - Raw word-list data files
data-scripts/                 - Python scripts to rebuild frequency_lists.json
```

## Key Design Decisions

- **Port fidelity**: Closely follows the original CoffeeScript implementation to ensure matching scores. References to upstream source are preserved in `@see` annotations.
- **Multiple binomial providers**: Binomial coefficient calculation is split into Float64, Int64, and GMP variants to handle precision requirements across PHP environments without mandatory extensions.
- **Immutable match flow**: `Matcher::get_matches()` returns new match objects rather than mutating shared state, making the scorer's DP algorithm thread-safe.
- **Stable sort**: `Matcher::usort_stable()` ensures deterministic output matching browser JavaScript behaviour.

## Extension Points

- **Custom matchers**: Call `Zxcvbn::add_matcher(MyMatcher::class)` — must implement `Match_Interface`.
- **Custom dictionaries**: Pass a `$ranked_dictionaries` array directly to `Dictionary_Match::match()`.

## Dependency Flow

```
Zxcvbn
  └─> Matcher       (omnimatch: runs all matchers)
  └─> Scorer        (dynamic programming over match list)
  └─> Time_Estimator (guesses → crack-time display)
  └─> Feedback      (score + sequence → user suggestions)
        └─> Base_Match::get_feedback() on each match
```
