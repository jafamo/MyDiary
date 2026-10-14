<?php

declare(strict_types=1);

namespace App\Controller\Api\Presenter;

use App\Controller\Api\ApiFormatter;
use App\Entity\DailySummary;
use App\Entity\Topic;

/**
 * Forma JSON de un resumen diario en la API. No expone el embedding.
 */
class SummaryPresenter
{
    public function __construct(
        private readonly TopicPresenter $topicPresenter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(DailySummary $dailySummary): array
    {
        return [
            'id' => $dailySummary->getId(),
            'date' => ApiFormatter::date($dailySummary->getDate()),
            'summary_text' => $dailySummary->getSummaryText(),
            'generated_at' => ApiFormatter::instant($dailySummary->getGeneratedAt()),
            'emoji_legend' => $dailySummary->getEmojiLegend(),
            'topics' => array_map(
                fn (Topic $topic): array => $this->topicPresenter->present($topic),
                array_values($dailySummary->getTopics()->toArray()),
            ),
            'model' => $dailySummary->getModel(),
            'prompt_tokens' => $dailySummary->getPromptTokens(),
            'completion_tokens' => $dailySummary->getCompletionTokens(),
            'generation_ms' => $dailySummary->getGenerationMs(),
        ];
    }
}
