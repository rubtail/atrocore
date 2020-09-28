<?php
declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;
use Treo\Migrations\V3Dot25Dot20;

/**
 * Installer listener
 *
 * @author r.ratsun@treolabs.com
 */
class Installer extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterInstallSystem(Event $event)
    {
        // generate Treo ID
        $this->generateTreoId();

        // create files in data dir
        $this->createDataFiles();

        // create scheduled jobs
        $this->createScheduledJobs();

        /**
         * Run after install script if it needs
         */
        $file = 'data/after_install_script.php';
        if (file_exists($file)) {
            include_once $file;
            unlink($file);
        }

        // fill treo store table
        (new V3Dot25Dot20($this->getEntityManager()->getPDO(), $this->getConfig()))->up();
    }

    /**
     * Generate Treo ID
     */
    protected function generateTreoId(): void
    {
        // generate id
        $treoId = \Treo\Services\Installer::generateTreoId();

        // set to config
        $this->getConfig()->set('treoId', $treoId);
        $this->getConfig()->save();
    }

    /**
     * Create needed files in data directory
     */
    protected function createDataFiles(): void
    {
        file_put_contents('data/notReadCount.json', '{}');
        file_put_contents('data/popupNotifications.json', '{}');
    }

    /**
     * Create scheduled jobs
     */
    protected function createScheduledJobs(): void
    {
        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('ComposerAutoUpdate', 'Auto-updating of modules', 'ComposerAutoUpdate', 'Active', '0 0 * * SUN')"
            );
        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('TreoCleanup','Unused data cleanup. Deleting old data and unused db tables, db columns, etc.','TreoCleanup','Active','0 0 1 * *')"
            );

        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('RestApiDocs','Generate REST API docs','RestApiDocs','Active','0 0 * * *')"
            );
    }
}