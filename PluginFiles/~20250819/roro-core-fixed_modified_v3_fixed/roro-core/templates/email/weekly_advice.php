<?php /** @var int $user_id */ ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>RoRo 週次アドバイス</title>
</head>
<body style="font-family: sans-serif; line-height:1.6;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:auto;border-collapse:collapse;">
	<tr><td style="background:#1C7A6F;color:#fff;padding:16px 24px;">
		<h1 style="margin:0;font-size:20px;">RoRo Weekly Advice</h1>
	</td></tr>
	<tr><td style="padding:24px;">
		<p><?php echo esc_html( get_user_meta( $user_id, 'nickname', true ) ); ?> さん、こんにちは！</p>
		<p>今週のおすすめコンテンツと近隣施設をまとめました。</p>

		<h2>🐾 おすすめ記事</h2>
		<ul>
			<li><a href="https://example.com/article/123">夏の熱中症対策</a></li>
			<li><a href="https://example.com/article/456">室内遊び 5 選</a></li>
		</ul>

		<h2>📍 近くの施設</h2>
		<p>最新レビューが高評価のペットカフェ:</p>
		<a href="https://goo.gl/maps/xxxxx">Dog Cafe Sunny</a>

		<hr style="margin:24px 0;">
		<p style="font-size:12px;color:#666;">
			このメールは RoRo に登録いただいた方へお送りしています。<br>
			通知設定の変更は <a href="https://example.com/profile">マイページ</a> から行えます。
		</p>
	</td></tr>
</table>
</body>
</html>
