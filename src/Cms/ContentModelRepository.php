<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

/** Configurable field sets for article categories. */
final class ContentModelRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT m.id, m.slug, m.name, COUNT(DISTINCT cm.category_id) AS category_count, COUNT(DISTINCT f.id) AS field_count FROM content_models m LEFT JOIN category_models cm ON cm.model_id = m.id LEFT JOIN model_fields f ON f.model_id = m.id GROUP BY m.id, m.slug, m.name ORDER BY m.id')->fetchAll();
    }

    public function model(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, slug, name FROM content_models WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function defaultModel(): array
    {
        $stmt = $this->pdo->query("SELECT id, slug, name FROM content_models WHERE slug = 'article'");
        return $stmt->fetch() ?: throw new InvalidArgumentException('默认文章模型不存在。');
    }

    public function saveModel(?int $id, string $name, string $slug): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100 || ! preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $slug)) {
            throw new InvalidArgumentException('模型名称或标识无效。');
        }
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO content_models (name, slug) VALUES (?, ?)');
            $stmt->execute(array($name, $slug));
            return (int) $this->pdo->lastInsertId();
        }
        if ($this->model($id) === null) {
            throw new InvalidArgumentException('模型不存在。');
        }
        if ($this->model($id)['slug'] === 'article' && $slug !== 'article') {
            throw new InvalidArgumentException('默认模型标识不能修改。');
        }
        $stmt = $this->pdo->prepare('UPDATE content_models SET name = ?, slug = ? WHERE id = ?');
        $stmt->execute(array($name, $slug, $id));
        return $id;
    }

    public function deleteModel(int $id): void
    {
        $model = $this->model($id);
        if ($model === null || $model['slug'] === 'article') {
            throw new InvalidArgumentException('默认模型不能删除，或模型不存在。');
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM category_models WHERE model_id = ?');
        $stmt->execute(array($id));
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('该模型仍被栏目使用，请先调整栏目模型。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM content_models WHERE id = ?');
        $stmt->execute(array($id));
    }

    public function modelForCategory(?int $categoryId): array
    {
        if ($categoryId !== null) {
            $stmt = $this->pdo->prepare('SELECT m.id, m.slug, m.name FROM category_models cm JOIN content_models m ON m.id = cm.model_id WHERE cm.category_id = ?');
            $stmt->execute(array($categoryId));
            if ($model = $stmt->fetch()) {
                return $model;
            }
        }
        return $this->defaultModel();
    }

    public function bindCategory(int $categoryId, int $modelId): void
    {
        if ($this->model($modelId) === null) {
            throw new InvalidArgumentException('所选内容模型不存在。');
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $stmt->execute(array($categoryId));
        if (! $stmt->fetchColumn()) {
            throw new InvalidArgumentException('栏目不存在。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM category_models WHERE category_id = ?');
        $stmt->execute(array($categoryId));
        $stmt = $this->pdo->prepare('INSERT INTO category_models (category_id, model_id) VALUES (?, ?)');
        $stmt->execute(array($categoryId, $modelId));
    }

    public function fields(int $modelId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, model_id, name, label, field_type, options_json, is_required, sort_order FROM model_fields WHERE model_id = ? ORDER BY sort_order, id');
        $stmt->execute(array($modelId));
        $fields = $stmt->fetchAll();
        foreach ($fields as &$field) {
            $field['options'] = json_decode($field['options_json'], true) ?: array();
        }
        return $fields;
    }

    public function field(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, model_id, name, label, field_type, options_json, is_required, sort_order FROM model_fields WHERE id = ?');
        $stmt->execute(array($id));
        $field = $stmt->fetch() ?: null;
        if ($field !== null) {
            $field['options'] = json_decode($field['options_json'], true) ?: array();
        }
        return $field;
    }

    public function saveField(?int $id, int $modelId, string $name, string $label, string $type, string $optionsText, bool $required, int $sortOrder): int
    {
        $name = trim($name);
        $label = trim($label);
        if ($this->model($modelId) === null || ! preg_match('/^ext_[a-z][a-z0-9_]{0,58}$/D', $name) || $label === '' || mb_strlen($label) > 100 || ! in_array($type, array('text', 'textarea', 'select', 'multi'), true) || $sortOrder < 0 || $sortOrder > 1000000) {
            throw new InvalidArgumentException('字段名称、类型或排序无效。字段标识须以 ext_ 开头。');
        }
        $options = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,，\r\n]+/u', $optionsText) ?: array()), static fn (string $value): bool => $value !== '')));
        if (count($options) > 50 || array_filter($options, static fn (string $value): bool => mb_strlen($value) > 100)) {
            throw new InvalidArgumentException('选项最多 50 个，每项不超过 100 字。');
        }
        if (in_array($type, array('select', 'multi'), true) && ! $options) {
            throw new InvalidArgumentException('选择类字段必须填写选项。');
        }
        if (! in_array($type, array('select', 'multi'), true)) {
            $options = array();
        }
        $encoded = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO model_fields (model_id, name, label, field_type, options_json, is_required, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(array($modelId, $name, $label, $type, $encoded, (int) $required, $sortOrder));
            return (int) $this->pdo->lastInsertId();
        }
        $field = $this->field($id);
        if ($field === null || (int) $field['model_id'] !== $modelId) {
            throw new InvalidArgumentException('字段不存在。');
        }
        $stmt = $this->pdo->prepare('UPDATE model_fields SET name = ?, label = ?, field_type = ?, options_json = ?, is_required = ?, sort_order = ? WHERE id = ?');
        $stmt->execute(array($name, $label, $type, $encoded, (int) $required, $sortOrder, $id));
        return $id;
    }

    public function deleteField(int $modelId, int $fieldId): void
    {
        $field = $this->field($fieldId);
        if ($field === null || (int) $field['model_id'] !== $modelId) {
            throw new InvalidArgumentException('字段不存在。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM model_fields WHERE id = ?');
        $stmt->execute(array($fieldId));
    }

    public function valuesForPost(int $postId): array
    {
        $stmt = $this->pdo->prepare('SELECT field_id, value FROM post_field_values WHERE post_id = ?');
        $stmt->execute(array($postId));
        $values = array();
        foreach ($stmt as $row) {
            $values[(int) $row['field_id']] = $row['value'];
        }
        return $values;
    }

    public function setValuesForPost(int $postId, ?int $categoryId, array $input): void
    {
        $model = $this->modelForCategory($categoryId);
        $values = array();
        foreach ($this->fields((int) $model['id']) as $field) {
            $id = (int) $field['id'];
            $raw = $input[(string) $id] ?? $input[$id] ?? ($field['field_type'] === 'multi' ? array() : '');
            if ($field['field_type'] === 'multi') {
                if (! is_array($raw) || count($raw) > 50 || array_filter($raw, static fn ($item): bool => ! is_string($item))) {
                    throw new InvalidArgumentException($field['label'] . ' 的选项无效。');
                }
                $chosen = array_values(array_unique($raw));
                if (array_diff($chosen, $field['options'])) {
                    throw new InvalidArgumentException($field['label'] . ' 包含未定义的选项。');
                }
                $value = implode(',', $chosen);
            } else {
                if (! is_string($raw)) {
                    throw new InvalidArgumentException($field['label'] . ' 的内容无效。');
                }
                $value = trim($raw);
                if ($field['field_type'] === 'select' && $value !== '' && ! in_array($value, $field['options'], true)) {
                    throw new InvalidArgumentException($field['label'] . ' 包含未定义的选项。');
                }
                if (mb_strlen($value) > ($field['field_type'] === 'textarea' ? 5000 : 200)) {
                    throw new InvalidArgumentException($field['label'] . ' 内容过长。');
                }
            }
            if ($field['is_required'] && $value === '') {
                throw new InvalidArgumentException($field['label'] . ' 不能为空。');
            }
            if ($value !== '') {
                $values[$id] = $value;
            }
        }
        $stmt = $this->pdo->prepare('DELETE FROM post_field_values WHERE post_id = ? AND field_id IN (SELECT id FROM model_fields WHERE model_id = ?)');
        $stmt->execute(array($postId, (int) $model['id']));
        $stmt = $this->pdo->prepare('INSERT INTO post_field_values (post_id, field_id, value) VALUES (?, ?, ?)');
        foreach ($values as $fieldId => $value) {
            $stmt->execute(array($postId, $fieldId, $value));
        }
    }

    public function publicFieldsForPost(int $postId, ?int $categoryId): array
    {
        $values = $this->valuesForPost($postId);
        $model = $this->modelForCategory($categoryId);
        $result = array();
        foreach ($this->fields((int) $model['id']) as $field) {
            if (isset($values[(int) $field['id']])) {
                $result[] = array('name' => $field['name'], 'label' => $field['label'], 'value' => $values[(int) $field['id']]);
            }
        }
        return $result;
    }
}
