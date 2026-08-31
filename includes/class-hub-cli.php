<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * WP-CLI commands for Constructor HUB.
 *
 * The WPCode migration used to run in a single admin POST that loaded every
 * historical lead into memory. With real volumes that times out and leaves a
 * partial state nobody can inspect. Batch work belongs on the command line.
 */
final class HUB_Tibox_CLI
{
    /**
     * Copies landings and leads from the historical WPCode implementation.
     *
     * ## OPTIONS
     *
     * [--batch=<number>]
     * : Rows per batch. Default 100.
     *
     * [--dry-run]
     * : Report what would be migrated without writing anything.
     *
     * ## EXAMPLES
     *
     *     wp hub migrate-wpcode --batch=200
     *     wp hub migrate-wpcode --dry-run
     *
     * @param string[]              $args
     * @param array<string,string>  $assoc_args
     */
    public function migrate_wpcode(array $args, array $assoc_args): void
    {
        $batch = max(1, (int) ($assoc_args['batch'] ?? 100));
        $dry_run = isset($assoc_args['dry-run']);

        $migrator = HUB_Tibox_Legacy_Migrator::instance();
        $pending = $migrator->pending_landing_ids();

        WP_CLI::log(sprintf('Landings pendientes: %d', count($pending)));

        if ($dry_run) {
            WP_CLI::log(sprintf('Leads pendientes: %d', $migrator->pending_lead_count()));
            WP_CLI::success('Dry run: no se escribió nada.');

            return;
        }

        $created = 0;
        foreach (array_chunk($pending, $batch) as $chunk) {
            foreach ($chunk as $legacy_id) {
                if ($migrator->migrate_landing($legacy_id) > 0) {
                    $created++;
                }
            }

            WP_CLI::log(sprintf('Migradas %d/%d landings…', $created, count($pending)));
        }

        $leads = HUB_Tibox_Landing_Lead_Store::instance()->migrate_legacy_leads($batch);
        WP_CLI::log(sprintf(
            'Leads: %d migrados, %d omitidos, %d restantes.',
            $leads['migrated'],
            $leads['skipped'],
            $leads['remaining']
        ));

        if ($leads['remaining'] > 0) {
            WP_CLI::warning('Quedan leads por migrar. Vuelve a ejecutar el comando.');

            return;
        }

        WP_CLI::success(sprintf('%d landings y %d leads migrados.', $created, $leads['migrated']));
    }

    /**
     * Lists HUB designs and their published version.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Filter by design type.
     *
     * @param string[]             $args
     * @param array<string,string> $assoc_args
     */
    public function designs(array $args, array $assoc_args): void
    {
        $type = sanitize_key((string) ($assoc_args['type'] ?? ''));
        $types = $type !== '' ? [$type] : HUB_Tibox_Design::type_keys();

        $rows = [];
        foreach ($types as $design_type) {
            foreach (HUB_Tibox_Design::list_by_type($design_type, 'any') as $design) {
                $live = HUB_Tibox_Version_Store::instance()->get_live($design->ID);

                $rows[] = [
                    'id' => $design->ID,
                    'tipo' => $design_type,
                    'slug' => $design->post_name,
                    'estado' => $design->post_status,
                    'version' => $live === null ? '—' : (string) $live['version'],
                ];
            }
        }

        WP_CLI\Utils\format_items('table', $rows, ['id', 'tipo', 'slug', 'estado', 'version']);
    }

    /**
     * Deletes leads older than the configured retention window.
     *
     * @param string[]             $args
     * @param array<string,string> $assoc_args
     */
    public function purge_leads(array $args, array $assoc_args): void
    {
        $deleted = HUB_Tibox_Landing_Lead_Store::instance()->purge_expired();

        if ($deleted === 0) {
            WP_CLI::log('Nada que eliminar. Revisa la retención en Constructor HUB → Configuración.');

            return;
        }

        WP_CLI::success(sprintf('%d leads eliminados por retención.', $deleted));
    }
}

WP_CLI::add_command('hub migrate-wpcode', [new HUB_Tibox_CLI(), 'migrate_wpcode']);
WP_CLI::add_command('hub designs', [new HUB_Tibox_CLI(), 'designs']);
WP_CLI::add_command('hub purge-leads', [new HUB_Tibox_CLI(), 'purge_leads']);
