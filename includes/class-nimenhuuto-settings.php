<?php

class Nimenhuuto_Settings {

	private const OPTION_KEY = 'wp_nimenhuuto_accounts';
	private const NONCE_ADD  = 'wp_nimenhuuto_add';
	private const NONCE_DEL  = 'wp_nimenhuuto_delete';
	private const ERROR_KEY  = 'wp_nimenhuuto';

	public function add_menu(): void {
		add_options_page(
			'Nimenhuuto Next Session',
			'Nimenhuuto',
			'manage_options',
			'wp-nimenhuuto',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->handle_form();

		$accounts = get_option( self::OPTION_KEY, [] );
		?>
		<div class="wrap">
			<h1>Nimenhuuto Next Session</h1>

			<?php settings_errors( self::ERROR_KEY ); ?>

			<h2>Accounts</h2>

			<?php if ( empty( $accounts ) ) : ?>
				<p>No accounts configured yet. Add one below.</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:14%">Sport</th>
							<th style="width:10%">Label</th>
							<th style="width:24%">Nimenhuuto URL</th>
							<th style="width:24%">iCal URL</th>
							<th style="width:20%">Shortcode</th>
							<th style="width:8%">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $accounts as $account ) : ?>
							<tr>
								<td><?php echo esc_html( $account['sport'] ); ?></td>
								<td><?php echo esc_html( $account['label'] ); ?></td>
								<td style="word-break:break-all"><?php echo esc_html( $account['url'] ); ?></td>
								<td style="word-break:break-all"><?php echo esc_html( $account['ical_url'] ?? '' ); ?></td>
								<td><code>[nimenhuuto_next_session account=&quot;<?php echo esc_attr( $account['id'] ); ?>&quot;]</code></td>
								<td>
									<form method="post" style="display:inline">
										<?php wp_nonce_field( self::NONCE_DEL ); ?>
										<input type="hidden" name="action" value="delete">
										<input type="hidden" name="account_id" value="<?php echo esc_attr( $account['id'] ); ?>">
										<button
											type="submit"
											class="button button-small button-link-delete"
											onclick="return confirm('Delete this account?')"
										>Delete</button>
									</form>
									&nbsp;
									<form method="post" style="display:inline">
										<?php wp_nonce_field( self::NONCE_DEL ); ?>
										<input type="hidden" name="action" value="clear_cache">
										<input type="hidden" name="account_id" value="<?php echo esc_attr( $account['id'] ); ?>">
										<button type="submit" class="button button-small">Clear cache</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2>Add Account</h2>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ADD ); ?>
				<input type="hidden" name="action" value="add">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sport">Sport</label></th>
						<td>
							<input type="text" id="sport" name="sport" class="regular-text" placeholder="Ice Hockey">
							<p class="description">e.g. Ice Hockey, Football, Futsal</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="label">Display label</label></th>
						<td>
							<input type="text" id="label" name="label" class="regular-text" placeholder="hockey">
							<p class="description">Used in &ldquo;The next <em>hockey</em> session is&hellip;&rdquo;</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="url">Nimenhuuto base URL</label></th>
						<td>
							<input type="url" id="url" name="url" class="regular-text" placeholder="https://yourteam.nimenhuuto.com/">
							<p class="description">The team URL, e.g. <code>https://kapylamaanantaiphc.nimenhuuto.com/</code></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ical_url">iCal / webcal URL</label></th>
						<td>
							<input type="text" id="ical_url" name="ical_url" class="large-text" placeholder="webcal://yourteam.nimenhuuto.com/calendar/ical?auth[user_id]=...">
							<p class="description">
								Paste the <strong>webcal://</strong> or <strong>https://</strong> link from
								<em>Nimenhuuto &rarr; Calendar &rarr; Download events &rarr; iCal-form</em>.
								You can choose a filter (All events, Training, Game, &hellip;) before copying the link.
								<br>The plugin converts <code>webcal://</code> to <code>https://</code> automatically.
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Add Account' ); ?>
			</form>

			<hr>

			<h2>Usage</h2>

			<h3>Shortcode</h3>
			<ul>
				<li>All accounts: <code>[nimenhuuto_next_session]</code></li>
				<li>Specific account: <code>[nimenhuuto_next_session account="&lt;id&gt;"]</code> (see ID in table above)</li>
			</ul>

			<h3>Gutenberg block</h3>
			<p>Search for <strong>Next Session</strong> in the block inserter. Use the sidebar to choose which account to display.</p>

			<h3>Output format</h3>
			<p><em>The next hockey session is Training Scrimmage Fri 7.8. at 21:00 &ndash; 22:30 Veikkaus Arena.</em></p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------

	private function handle_form(): void {
		if ( empty( $_POST['action'] ) ) {
			return;
		}

		$action = sanitize_key( $_POST['action'] );

		if ( $action === 'add' ) {
			$this->handle_add();
		} elseif ( $action === 'delete' ) {
			$this->handle_delete();
		} elseif ( $action === 'clear_cache' ) {
			$this->handle_clear_cache();
		}
	}

	private function handle_add(): void {
		check_admin_referer( self::NONCE_ADD );

		$sport    = sanitize_text_field( $_POST['sport'] ?? '' );
		$label    = sanitize_text_field( $_POST['label'] ?? '' );
		$url      = esc_url_raw( $_POST['url'] ?? '' );
		$ical_url = sanitize_text_field( $_POST['ical_url'] ?? '' );

		if ( empty( $sport ) || empty( $url ) ) {
			add_settings_error( self::ERROR_KEY, 'missing', 'Sport and Nimenhuuto URL are required.', 'error' );
			return;
		}

		$accounts   = get_option( self::OPTION_KEY, [] );
		$accounts[] = [
			'id'       => wp_generate_uuid4(),
			'sport'    => $sport,
			'label'    => $label ?: strtolower( $sport ),
			'url'      => trailingslashit( $url ),
			'ical_url' => $ical_url,
		];

		update_option( self::OPTION_KEY, array_values( $accounts ) );
		add_settings_error( self::ERROR_KEY, 'added', 'Account added.', 'success' );
	}

	private function handle_delete(): void {
		check_admin_referer( self::NONCE_DEL );

		$id       = sanitize_text_field( $_POST['account_id'] ?? '' );
		$accounts = get_option( self::OPTION_KEY, [] );
		$accounts = array_values( array_filter( $accounts, fn( $a ) => $a['id'] !== $id ) );

		update_option( self::OPTION_KEY, $accounts );
		( new Nimenhuuto_Fetcher() )->clear_cache( $id );
		add_settings_error( self::ERROR_KEY, 'deleted', 'Account deleted.', 'success' );
	}

	private function handle_clear_cache(): void {
		check_admin_referer( self::NONCE_DEL );

		$id = sanitize_text_field( $_POST['account_id'] ?? '' );
		( new Nimenhuuto_Fetcher() )->clear_cache( $id );
		add_settings_error( self::ERROR_KEY, 'cleared', 'Cache cleared.', 'success' );
	}
}
