<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923161809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade métricas de consumo de IA (nullable) a transcription y daily_summary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE daily_summary ADD prompt_tokens INT DEFAULT NULL');
        $this->addSql('ALTER TABLE daily_summary ADD completion_tokens INT DEFAULT NULL');
        $this->addSql('ALTER TABLE daily_summary ADD generation_ms INT DEFAULT NULL');
        $this->addSql('ALTER TABLE daily_summary ADD model VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transcription ADD processing_ms INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transcription ADD model VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE daily_summary DROP prompt_tokens');
        $this->addSql('ALTER TABLE daily_summary DROP completion_tokens');
        $this->addSql('ALTER TABLE daily_summary DROP generation_ms');
        $this->addSql('ALTER TABLE daily_summary DROP model');
        $this->addSql('ALTER TABLE transcription DROP processing_ms');
        $this->addSql('ALTER TABLE transcription DROP model');
    }
}
