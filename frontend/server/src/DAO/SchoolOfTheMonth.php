<?php

namespace OmegaUp\DAO;

/**
 * SchoolOfTheMonth Data Access Object (DAO).
 *
 * Esta clase contiene toda la manipulacion de bases de datos que se necesita
 * para almacenar de forma permanente y recuperar instancias de objetos
 * {@link \OmegaUp\DAO\VO\SchoolOfTheMonth}.
 *
 * @author carlosabcs
 * @access public
 */
class SchoolOfTheMonth extends \OmegaUp\DAO\Base\SchoolOfTheMonth {
  /**
   * Gets the top 100 schools that got the biggest scores from the problems
   * their users solved.
   *
   * @return list<array{school_id: int, name: string, country_id: string, score: float}>
   */
    public static function calculateSchoolsOfMonth(
        string $startDate,
        string $finishDate,
        int $limit
    ): array {
        $sql = '
            SELECT
                s.school_id,
                s.name,
                s.country_id,
                SUM(ROUND(100 / LOG(2, distinct_school_problems.accepted+1), 0)) AS score
            FROM
                Schools s
            INNER JOIN
                (
                    SELECT
                        su.school_id,
                        p.accepted,
                        MIN(su.time) AS first_ac_time
                    FROM
                        Submissions su
                    INNER JOIN
                        Runs r ON r.run_id = su.current_run_id
                    INNER JOIN
                        Problems p ON p.problem_id = su.problem_id
                    WHERE
                        r.verdict = "AC"
                        AND p.visibility >= 1
                        AND su.school_id IS NOT NULL
                    GROUP BY
                        su.school_id,
                        su.problem_id
                    HAVING
                        first_ac_time BETWEEN ? AND ?
                ) AS distinct_school_problems
            ON
                distinct_school_problems.school_id = s.school_id
            WHERE
                NOT EXISTS (
                    SELECT
                        sotm.school_id,
                        MAX(time) latest_time
                    FROM
                        School_Of_The_Month as sotm
                    WHERE
                        sotm.school_id = s.school_id
                        AND (sotm.selected_by IS NOT NULL OR sotm.rank = 1)
                    GROUP BY
                        sotm.school_id
                    HAVING
                        DATE_ADD(latest_time, INTERVAL 1 YEAR) >= ?
                )
            GROUP BY
                s.school_id
            ORDER BY
                score DESC
            LIMIT ?;';

        $args = [$startDate, $finishDate, $finishDate, $limit];

        /** @var list<array{school_id: int, name: string, country_id: string, score: float}> */
        return \OmegaUp\MySQLConnection::getInstance()->GetAll(
            $sql,
            $args
        );
    }

    /**
     * @return list<array{school_id: int, name: string, country_id: string, score: float}>
     */
    public static function calculateSchoolsOfMonthByGivenDate(
        string $date,
        int $rowcount = 100
    ): array {
        $date = new \DateTimeImmutable($date);
        $firstDayOfLastMonth = $date->modify('first day of last month');
        $startTime = $firstDayOfLastMonth->format('Y-m-d');
        $firstDayOfCurrentMonth = $date->modify('first day of this month');
        $endTime = $firstDayOfCurrentMonth->format('Y-m-d');
        return self::calculateSchoolsOfMonth($startTime, $endTime, $rowcount);
    }

    /**
     * @return \OmegaUp\DAO\VO\SchoolOfTheMonth[]
     */
    public static function getByTime(
        string $time
    ): array {
        $sql = '
            SELECT
                *
            FROM
                School_Of_The_Month
            WHERE
                time = ?;';

        $schools = [];
        foreach (
            \OmegaUp\MySQLConnection::getInstance()->GetAll(
                $sql,
                [$time]
            ) as $row
        ) {
            array_push($schools, new \OmegaUp\DAO\VO\SchoolOfTheMonth($row));
        }
        return $schools;
    }
}