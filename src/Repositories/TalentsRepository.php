<?php

declare(strict_types=1);

class TalentsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function summary(): array
    {
        return $this->db->fetchAll(
            'SELECT t.id, t.name, t.role, t.seniority, t.weekly_capacity, t.hourly_cost, t.hourly_rate, t.availability, GROUP_CONCAT(s.name) AS skills
             FROM talents t LEFT JOIN talent_skills ts ON ts.talent_id = t.id LEFT JOIN skills s ON s.id = ts.skill_id GROUP BY t.id ORDER BY t.name'
        );
    }
}
