<?php
/**
 * Contact action library storage.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Repositories;

use Bigloop\CallButton\PostTypes\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Actions are reusable: widgets reference them by id.
 */
final class ActionRepository extends PostRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function post_type(): string {
		return PostTypes::ACTION;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function changed_hook(): string {
		return 'bigloop_cb/actions_changed';
	}
}
