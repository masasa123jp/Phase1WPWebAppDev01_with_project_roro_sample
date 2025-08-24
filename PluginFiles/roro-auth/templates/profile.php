<?php
if (!defined('ABSPATH')) exit;
/** @var array $messages */
/** @var array $user_meta */
/** @var array $pets */
/** @var array $breed_options */
/** @var WP_User $user */
$messages = RORO_Auth_Utils::messages();
$flash = RORO_Auth_Utils::consume_flash();
$current_url = esc_url(add_query_arg([], home_url(add_query_arg([], $_SERVER['REQUEST_URI']))));
?>
<div class="roro-profile-wrap">

  <?php if ($flash): ?>
    <div class="roro-auth-flash roro-auth-<?php echo esc_attr($flash['type']); ?>">
      <?php echo esc_html($flash['message']); ?>
    </div>
  <?php endif; ?>

  <h2 class="roro-section-title"><?php echo esc_html($messages['profile_title']); ?></h2>
  <p class="roro-section-sub"><?php echo esc_html($messages['profile_subtitle']); ?></p>

  <form class="roro-profile-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('roro_profile_update', '_roro_profile_nonce'); ?>
    <input type="hidden" name="action" value="roro_profile_update">
    <input type="hidden" name="_roro_redirect" value="<?php echo esc_attr($current_url); ?>">

    <div class="roro-profile-grid">
      <div class="roro-profile-avatar">
        <label class="roro-label"><?php echo esc_html($messages['profile_avatar']); ?></label>
        <div class="roro-avatar-box">
          <?php if (!empty($avatar_url)): ?>
            <img src="<?php echo esc_url($avatar_url); ?>" alt="avatar" id="roro-avatar-preview">
          <?php else: ?>
            <div class="roro-avatar-placeholder" id="roro-avatar-preview"><?php echo esc_html($messages['profile_avatar_placeholder']); ?></div>
          <?php endif; ?>
        </div>
        <input type="file" name="avatar" accept="image/*">
      </div>

      <div class="roro-profile-fields">
        <p>
          <label class="roro-label"><?php echo esc_html($messages['profile_display_name']); ?></label>
          <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" required>
        </p>
        <p>
          <label class="roro-label"><?php echo esc_html($messages['profile_email']); ?></label>
          <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required>
        </p>
        <p>
          <label class="roro-label"><?php echo esc_html($messages['profile_lang']); ?></label>
          <select name="lang">
            <?php
              $langs = ['ja'=>'日本語','en'=>'English','zh'=>'中文','ko'=>'한국어'];
              foreach ($langs as $k=>$label):
            ?>
              <option value="<?php echo esc_attr($k); ?>" <?php selected($k, $lang); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
          </select>
        </p>

        <p><label class="roro-label"><?php echo esc_html($messages['profile_phone']); ?></label>
          <input type="text" name="roro_phone" value="<?php echo esc_attr($user_meta['roro_phone']); ?>">
        </p>
        <p><label class="roro-label"><?php echo esc_html($messages['profile_postal']); ?></label>
          <input type="text" name="roro_postal" value="<?php echo esc_attr($user_meta['roro_postal']); ?>">
        </p>
        <p><label class="roro-label"><?php echo esc_html($messages['profile_region']); ?></label>
          <input type="text" name="roro_region" value="<?php echo esc_attr($user_meta['roro_region']); ?>" placeholder="<?php echo esc_attr($messages['profile_region_ph']); ?>">
        </p>
        <p><label class="roro-label"><?php echo esc_html($messages['profile_city']); ?></label>
          <input type="text" name="roro_city" value="<?php echo esc_attr($user_meta['roro_city']); ?>">
        </p>
        <p><label class="roro-label"><?php echo esc_html($messages['profile_address1']); ?></label>
          <input type="text" name="roro_address1" value="<?php echo esc_attr($user_meta['roro_address1']); ?>">
        </p>
        <p><label class="roro-label"><?php echo esc_html($messages['profile_address2']); ?></label>
          <input type="text" name="roro_address2" value="<?php echo esc_attr($user_meta['roro_address2']); ?>">
        </p>
        <p><label class="roro-label"><?php echo esc_html($messages['profile_country']); ?></label>
          <input type="text" name="roro_country" value="<?php echo esc_attr($user_meta['roro_country']); ?>" placeholder="JP">
        </p>
      </div>
    </div>

    <div class="roro-actions">
      <button class="roro-btn" type="submit"><?php echo esc_html($messages['profile_save_button']); ?></button>
    </div>
  </form>


  <h2 class="roro-section-title"><?php echo esc_html($messages['pets_title']); ?></h2>

  <!-- 追加フォーム -->
  <form class="roro-pet-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('roro_pet_create', '_roro_pet_nonce'); ?>
    <input type="hidden" name="action" value="roro_pet_create">
    <input type="hidden" name="_roro_redirect" value="<?php echo esc_attr($current_url); ?>">
    <fieldset class="roro-fieldset">
      <legend><?php echo esc_html($messages['pet_add_title']); ?></legend>

      <p>
        <label class="roro-label"><?php echo esc_html($messages['pet_name']); ?></label>
        <input type="text" name="pet_name" required>
      </p>

      <p>
        <label class="roro-label"><?php echo esc_html($messages['pet_species']); ?></label>
        <select name="pet_species" id="roro-pet-species">
          <option value="dog"><?php echo esc_html($messages['pet_species_dog']); ?></option>
          <option value="cat"><?php echo esc_html($messages['pet_species_cat']); ?></option>
          <option value="other"><?php echo esc_html($messages['pet_species_other']); ?></option>
        </select>
      </p>

      <p>
        <label class="roro-label"><?php echo esc_html($messages['pet_breed']); ?></label>
        <select name="pet_breed_id" id="roro-breed-select">
          <option value=""><?php echo esc_html($messages['breed_placeholder']); ?></option>
          <?php foreach (($breed_options['dog'] ?? []) as $b): ?>
            <option value="<?php echo (int)$b['id']; ?>" data-sp="dog"><?php echo esc_html($b['name']); ?></option>
          <?php endforeach; ?>
          <?php foreach (($breed_options['cat'] ?? []) as $b): ?>
            <option value="<?php echo (int)$b['id']; ?>" data-sp="cat"><?php echo esc_html($b['name']); ?></option>
          <?php endforeach; ?>
          <?php foreach (($breed_options['other'] ?? []) as $b): ?>
            <option value="<?php echo (int)$b['id']; ?>" data-sp="other"><?php echo esc_html($b['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="pet_breed" id="roro-breed-fallback" placeholder="<?php echo esc_attr($messages['breed_fallback']); ?>">
        <small class="roro-help"><?php echo esc_html($messages['breed_from_master']); ?></small>
      </p>

      <p>
        <label class="roro-label"><?php echo esc_html($messages['pet_sex']); ?></label>
        <select name="pet_sex">
          <option value="male"><?php echo esc_html($messages['pet_sex_male']); ?></option>
          <option value="female"><?php echo esc_html($messages['pet_sex_female']); ?></option>
        </select>
      </p>

      <p>
        <label class="roro-label"><?php echo esc_html($messages['pet_birthdate']); ?></label>
        <input type="date" name="pet_birthdate">
      </p>

      <p>
        <label class="roro-label"><?php echo esc_html($messages['pet_weight']); ?></label>
        <input type="number" step="0.1" name="pet_weight">
      </p>

      <p>
        <label class="roro-label"><?php echo esc_html($messages['pet_avatar']); ?></label>
        <input type="file" name="pet_avatar" accept="image/*">
      </p>

      <div class="roro-actions">
        <button class="roro-btn" type="submit"><?php echo esc_html($messages['pet_add_button']); ?></button>
      </div>
    </fieldset>
  </form>

  <!-- 一覧（編集・削除） -->
  <?php if (!empty($pets)): ?>
    <table class="roro-pet-table">
      <thead>
        <tr>
          <th><?php echo esc_html($messages['pet_name']); ?></th>
          <th><?php echo esc_html($messages['pet_species']); ?></th>
          <th><?php echo esc_html($messages['pet_breed']); ?></th>
          <th><?php echo esc_html($messages['pet_birthdate']); ?></th>
          <th><?php echo esc_html($messages['pet_sex']); ?></th>
          <th><?php echo esc_html($messages['pet_weight']); ?></th>
          <th><?php echo esc_html($messages['pet_avatar']); ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pets as $p): ?>
        <tr>
          <td>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="roro-inline-form">
              <?php wp_nonce_field('roro_pet_update', '_roro_pet_nonce'); ?>
              <input type="hidden" name="action" value="roro_pet_update">
              <input type="hidden" name="pet_id" value="<?php echo esc_attr($p['id']); ?>">
              <input type="hidden" name="_roro_redirect" value="<?php echo esc_attr($current_url); ?>">
              <input type="text" name="pet_name" value="<?php echo esc_attr($p['name'] ?? $p['pet_name'] ?? ''); ?>" required>
          </td>
          <td>
              <select name="pet_species" class="roro-pet-species-inline">
                <?php
                  $spv = esc_attr(strtolower($p['species'] ?? 'other'));
                  $opts = ['dog'=>$messages['pet_species_dog'], 'cat'=>$messages['pet_species_cat'], 'other'=>$messages['pet_species_other']];
                  foreach ($opts as $kv=>$lbl): ?>
                    <option value="<?php echo esc_attr($kv); ?>" <?php selected($kv, $spv); ?>><?php echo esc_html($lbl); ?></option>
                  <?php endforeach; ?>
              </select>
          </td>
          <td>
              <input type="hidden" name="pet_breed_id" value="<?php echo isset($p['breed_id']) ? (int)$p['breed_id'] : 0; ?>">
              <input type="text"   name="pet_breed" value="<?php echo esc_attr($p['breed_name'] ?? ''); ?>">
          </td>
          <td><input type="date" name="pet_birthdate" value="<?php echo esc_attr($p['birthdate'] ?? ''); ?>"></td>
          <td>
              <select name="pet_sex">
                <option value="male"   <?php selected('male',   strtolower($p['sex'] ?? '')); ?>><?php echo esc_html($messages['pet_sex_male']); ?></option>
                <option value="female" <?php selected('female', strtolower($p['sex'] ?? '')); ?>><?php echo esc_html($messages['pet_sex_female']); ?></option>
              </select>
          </td>
          <td><input type="number" step="0.1" name="pet_weight" value="<?php echo isset($p['weight']) ? esc_attr($p['weight']) : ''; ?>"></td>
          <td>
              <?php if (!empty($p['picture_url'])): ?>
                <img class="roro-pet-thumb" src="<?php echo esc_url($p['picture_url']); ?>" alt="">
              <?php endif; ?>
              <input type="file" name="pet_avatar" accept="image/*">
          </td>
          <td class="roro-actions">
              <button class="roro-btn" type="submit"><?php echo esc_html($messages['pet_update_button']); ?></button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="roro-inline-form roro-delete-form">
              <?php wp_nonce_field('roro_pet_delete', '_roro_pet_nonce'); ?>
              <input type="hidden" name="action" value="roro_pet_delete">
              <input type="hidden" name="pet_id" value="<?php echo esc_attr($p['id']); ?>">
              <input type="hidden" name="_roro_redirect" value="<?php echo esc_attr($current_url); ?>">
              <button class="roro-btn roro-danger" type="submit"><?php echo esc_html($messages['pet_delete_button']); ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="roro-empty"><?php echo esc_html($messages['pets_empty']); ?></p>
  <?php endif; ?>

</div>
