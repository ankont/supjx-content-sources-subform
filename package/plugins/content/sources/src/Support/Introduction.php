<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

require_once __DIR__ . '/EmailHtml.php';

/** Native taxonomy identity/image only; the host owns description preparation. */
final class Introduction
{
    public static function model(object $entity, string $type, string $root): array
    {
        $params = json_decode((string) ($entity->params ?? '{}'), true) ?: [];
        $images = json_decode((string) ($entity->images ?? '{}'), true) ?: [];
        $image = $type === 'categories' ? ($params['image'] ?? '') : (($images['image_intro'] ?? '') ?: ($images['image_fulltext'] ?? ''));
        return ['id' => (int) $entity->id, 'title' => (string) $entity->title,
            'description' => (string) ($entity->description ?? ''),
            'image' => is_string($image) ? EmailHtml::url(explode('#', $image, 2)[0], $root, true) : ''];
    }
}
