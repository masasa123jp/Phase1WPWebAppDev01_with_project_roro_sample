<?php if (!defined('ABSPATH')) exit; ?>
<div class="roro-profile">
  <h2>Profile</h2>
  <?php if (!is_user_logged_in()): ?>
    <p>ログインが必要です。<a href="<?php echo esc_url( site_url('/login/') ); ?>">ログイン</a></p>
  <?php else: ?>
  <form id="roro-profile-form">
    <div class="grid-2">
      <label>郵便番号<input type="text" name="postal_code" maxlength="7"></label>
      <label>国コード<input type="text" name="country_code" maxlength="2" placeholder="JP等"></label>
      <label>都道府県<input type="text" name="prefecture"></label>
      <label>市区町村<input type="text" name="city"></label>
      <label>住所1<input type="text" name="address_line1"></label>
      <label>住所2<input type="text" name="address_line2"></label>
      <label>建物名<input type="text" name="building"></label>
    </div>
    <button type="submit">保存</button>
  </form>
  <?php endif; ?>
</div>
