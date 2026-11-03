<?php
/**
 * Schedules storage.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Repositories;

use Bigloop\CallButton\PostTypes\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Reusable schedules referenced by widgets.
 */
final class ScheduleRepository extends PostRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function post_type(): string {
		return PostTypes::SCHEDULE;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function changed_hook(): string {
		return 'bigloop_cb/schedules_changed';
	}
}
