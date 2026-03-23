<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Conditions {

    /**
     * Evaluate whether a set of conditions passes.
     *
     * @param array    $conditions  Config array with keys: enabled, operator, rules[].
     * @param callable $resolver    Accepts a field ID string, returns its value string.
     * @return bool  True if conditions pass (or conditions are disabled/empty).
     */
    public static function passes( array $conditions, callable $resolver ): bool {
        if ( empty( $conditions['enabled'] ) || empty( $conditions['rules'] ) ) {
            return true;
        }

        $operator = strtoupper( $conditions['operator'] ?? 'AND' );
        $results  = [];

        foreach ( (array) $conditions['rules'] as $rule ) {
            $results[] = self::evaluate_rule( $rule, $resolver );
        }

        if ( $operator === 'OR' ) {
            return in_array( true, $results, true );
        }

        // AND — all must pass.
        return ! in_array( false, $results, true );
    }

    private static function evaluate_rule( array $rule, callable $resolver ): bool {
        $field_id = $rule['field_id'] ?? '';
        $compare  = $rule['compare']  ?? 'equals';
        $expected = $rule['value']    ?? '';
        $actual   = (string) $resolver( $field_id );

        switch ( $compare ) {
            case 'equals':       return $actual === $expected;
            case 'not_equals':   return $actual !== $expected;
            case 'contains':     return $expected !== '' && str_contains( $actual, $expected );
            case 'not_contains': return $expected === '' || ! str_contains( $actual, $expected );
            case 'starts_with':  return $expected !== '' && str_starts_with( $actual, $expected );
            case 'ends_with':    return $expected !== '' && str_ends_with( $actual, $expected );
            case 'is_empty':     return trim( $actual ) === '';
            case 'is_not_empty': return trim( $actual ) !== '';
            default:             return true;
        }
    }
}
