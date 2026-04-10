<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410014601 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index on likes (user_id, photo_id) to prevent duplicate likes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_likes_user_photo ON likes (user_id, photo_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_likes_user_photo');
    }
}
