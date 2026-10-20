<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020170343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'app_user.ui_theme: tema visual elegido por el usuario (moderno/cuaderno)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD ui_theme VARCHAR(20) DEFAULT \'moderno\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP ui_theme');
    }
}
