# Windows環境でのセットアップ

Windows環境でアプリを動作させる場合も、基本的に静的ファイルの配布だけで動きます。以下の手順でセットアップしてください。

1. **ZIPを展開**：`project_roro_webapp_final_updated19.zip`を右クリックし、「すべて展開」を実行します。`project_root`フォルダが作成されます。
2. **Pythonをインストール**：Python公式サイトからWindows用インストーラーを取得し、インストール時に「Add python.exe to PATH」にチェックを入れます。
3. **サーバー起動**：`cmd`または`PowerShell`を開き、展開した`project_root`ディレクトリへ移動して以下を実行します。

```
cd C:\path\to\project_root
python -m http.server 8000
```

4. **ブラウザでアクセス**：`http://localhost:8000/index.html`にアクセスし、アプリが表示されることを確認します。
5. **キーの設定**：Linux/Macと同様に、APIキーやIDをBase64エンコードした値を`index.html`と`map.html`の`meta`タグに貼り付けます。PowerShellでも`[System.Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes('YOUR_KEY'))`などでエンコード可能です。
6. **既存のWAMP/XAMPPを使う場合（任意）**：Apache/PHP環境が既にあるなら、プロジェクトを`htdocs`などの公開ディレクトリに置いて運用することもできます。ただし`meta`タグのキー設定を忘れないようにしてください。

