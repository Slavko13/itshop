<?php
/**
 * Customer addresses overview.
 *
 * @package itsoft
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="account-section-head">
	<div>
		<h2>Личные данные</h2>
		<p>Для электронных заказов достаточно e-mail и телефона покупателя.</p>
	</div>
</div>

<div class="account-empty-state">
	<p>Изменить контактные данные можно в настройках профиля.</p>
	<a class="button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">Перейти к личным данным</a>
</div>
