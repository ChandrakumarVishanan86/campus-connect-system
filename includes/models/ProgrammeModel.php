<?php
declare(strict_types=1);

final class ProgrammeModel extends BaseModel
{
    protected static string $table = 'programmes';
    protected static array $fields = [
        'code'      => ['label' => 'Code', 'required' => true, 'max' => 10, 'upper' => true, 'unique' => true, 'regex' => '/^[A-Z0-9]{2,10}$/', 'regexMsg' => '2-10 letters or digits.'],
        'name'      => ['label' => 'Programme name', 'required' => true, 'max' => 100, 'unique' => true],
        'is_active' => ['label' => 'Active', 'type' => 'bool'],
    ];
    protected static function baseSelect(): string { return 'SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.programme_id = t.id) AS people FROM programmes t'; }
    protected static function orderBy(): string { return 'ORDER BY t.code'; }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void { Audit::log($isNew ? 'create' : 'update', 'programme', $id, $d['code']); }
    public static function beforeDelete(int $id): ?string
    {
        $s = db()->prepare('SELECT COUNT(*) FROM users WHERE programme_id = ?'); $s->execute([$id]);
        return $s->fetchColumn() > 0 ? 'People are enrolled in this programme. Mark it inactive instead.' : null;
    }
}
