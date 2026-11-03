<?php
/**
 * Custom design presets storage.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Repositories;

use Bigloop\CallButton\PostTypes\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Custom presets saved by the site owner. Config holds { description, config: partial style }.
 */
final class PresetRepository extends PostRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function post_type(): string {
		return PostTypes::PRESET;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function changed_hook(): string {
		return 'bigloop_cb/presets_changed';
	}
}
