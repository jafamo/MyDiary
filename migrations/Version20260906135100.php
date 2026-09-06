<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906135100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activa la extensión pgvector y añade la columna embedding vector(768) a transcription y daily_summary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql('ALTER TABLE transcription ADD embedding vector(768) DEFAULT NULL');
        $this->addSql('ALTER TABLE daily_summary ADD embedding vector(768) DEFAULT NULL');
        $this->addSql('CREATE INDEX transcription_embedding_hnsw ON transcription USING hnsw (embedding vector_cosine_ops)');
        $this->addSql('CREATE INDEX daily_summary_embedding_hnsw ON daily_summary USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX daily_summary_embedding_hnsw');
        $this->addSql('DROP INDEX transcription_embedding_hnsw');
        $this->addSql('ALTER TABLE daily_summary DROP embedding');
        $this->addSql('ALTER TABLE transcription DROP embedding');
    }
}
