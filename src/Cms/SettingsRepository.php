<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class SettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(string $defaultName): array
    {
        $values = array('site_name' => $defaultName, 'description' => '', 'footer_text' => 'Powered by FangdaysCMS');
        foreach ($this->pdo->query('SELECT name, value FROM site_settings') as $row) {
            if (array_key_exists($row['name'], $values)) {
                $values[$row['name']] = $row['value'];
            }
        }
        return $values;
    }

    public function save(string $name, string $description, string $footer): void
    {
        $name = trim($name);
        $description = trim($description);
        $footer = trim($footer);
        if ($name === '' || mb_strlen($name) > 100 || mb_strlen($description) > 300 || mb_strlen($footer) > 150) {
            throw new InvalidArgumentException('站点名称、简介或页脚长度无效。');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM site_settings WHERE name = ?');
            $insert = $this->pdo->prepare('INSERT INTO site_settings (name, value) VALUES (?, ?)');
            foreach (array('site_name' => $name, 'description' => $description, 'footer_text' => $footer) as $key => $value) {
                $stmt->execute(array($key));
                $insert->execute(array($key, $value));
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }
}
