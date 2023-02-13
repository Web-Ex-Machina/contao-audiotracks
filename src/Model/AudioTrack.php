<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Model;

/**
 * Reads and writes items.
 */
class AudioTrack extends \WEM\UtilsBundle\Model\Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_wem_audiotrack';

    /**
     * Generic statements format.
     *
     * @param string $strField    [Column to format]
     * @param mixed  $varValue    [Value to use]
     * @param string $strOperator [Operator to use, default "="]
     *
     * @return array
     */
    public static function formatStatement($strField, $varValue, $strOperator = '=')
    {
        try {
            $arrColumns = [];
            $t = static::$strTable;

            switch ($strField) {
                case 'pid':
                    if (!$varValue || !\is_array($varValue)) {
                        $varValue = [$varValue];
                    }

                    $arrColumns[] = sprintf("$t.pid IN('%s')", implode("','", $varValue));
                break;

                case 'search':
                    $arrColumns[] = sprintf(
                        "(
                        ($t.training_center IN (
                            SELECT tc.id
                            FROM %s tc
                            WHERE tc.title LIKE '%%%%%s%%%%'
                            OR tc.city LIKE '%%%%%s%%%%'
                        ))
                        OR
                        ($t.id IN (
                            SELECT eso.pid
                            FROM %s eso
                            INNER JOIN %s ro ON eso.registration = ro.id
                            INNER JOIN %s o ON ro.operator = o.id
                            WHERE o.lastname LIKE '%%%%%s%%%%'
                            OR o.firstname LIKE '%%%%%s%%%%'
                        ))
                        OR
                        ($t.id IN (
                            SELECT eso.pid
                            FROM %s eso
                            INNER JOIN %s ro ON eso.registration = ro.id
                            INNER JOIN %s r ON ro.pid = r.id
                            INNER JOIN %s c ON r.company = c.id
                            WHERE c.title LIKE '%%%%%s%%%%'
                        ))
                        )",
                        TrainingCenter::getTable(),
                        $varValue,
                        $varValue,
                        ExamSessionOperator::getTable(),
                        RegistrationOperator::getTable(),
                        Operator::getTable(),
                        $varValue,
                        $varValue,
                        ExamSessionOperator::getTable(),
                        RegistrationOperator::getTable(),
                        Registration::getTable(),
                        Company::getTable(),
                        $varValue,
                    );
                break;

                // Load parent
                default:
                    $arrColumns = array_merge($arrColumns, parent::formatStatement($strField, $varValue, $strOperator));
            }

            return $arrColumns;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
