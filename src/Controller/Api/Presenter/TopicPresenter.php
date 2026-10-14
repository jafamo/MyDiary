<?php

declare(strict_types=1);

namespace App\Controller\Api\Presenter;

use App\Entity\Topic;

class TopicPresenter
{
    /**
     * @return array{id: int|null, name: string}
     */
    public function present(Topic $topic): array
    {
        return [
            'id' => $topic->getId(),
            'name' => $topic->getName(),
        ];
    }
}
