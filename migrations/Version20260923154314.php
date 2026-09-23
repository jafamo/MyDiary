<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923154314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade la columna emoji_legend (JSON, nullable) a daily_summary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE daily_summary ADD emoji_legend JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE daily_summary DROP emoji_legend');
    }
}
