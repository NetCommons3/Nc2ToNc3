Nc2ToNc3
==============

Migration plugin to Nc3 from Nc2 for NetComomns3

Nc2ToNc3(移行ツール)は、同一サーバにNC2のデータとNC3サイトを用意して移行するプラグインです。
NC2最新版（2.4.2.1）からNC3最新版（3.1.5）に移行します。

### 手順

1. [NC2を最新版にアップデート](#NC2を最新版にアップデート)
1. [NC2の準備](#nc2の準備)
1. [NC3の準備](#NC3の準備)
1. [NC3のバックアップ](#nc3のバックアップ)
1. [移行ツール実行](#移行ツール実行)

#### NC2を最新版にアップデート

NC2が最新版でなかったら、最新版2.4.2.1にアップデートします。

[NC2を最新版2.4.2.1にアップデート（外部リンク）](https://nc2.netcommons.org/ダウンロード/コアパッケージ/)

#### NC2の準備

現状NC2のDB及びNC2のアップロードファイルを取得して、NC3と同じ環境にDBインポート及びファイル配置をしてください。

#### NC3の準備

NC3を新規インストールします。
[NC3インストール（外部リンク）](https://www.netcommons.org/NetCommons3/download)

NC3を既にインストール済みの場合、最新版(3.1.5)にアップデートします。
[NC3アップデート（外部リンク）](https://nc2.netcommons.org/ヘルプデスク/NetCommons3/バージョンアップ方法/)

##### NC3にNc2ToNc3配置

NC3.1.5よりNc2ToNc3(移行ツール)が同梱されるようになったため、  
app/Plugin配下に配置されています。

```
NetCommons3のパス/app/Plugin/Nc2ToNc3
```

##### Nc2ToNc3のマイグレーション実行

~~cakeコマンドのパス/cake Migrations.migration run -p Nc2ToNc3  -c master -i master~~  
NC3.1.5よりNc2ToNc3(移行ツール)が同梱されるようになったため、マイグレーション実行は不要になりました。

#### NC3のバックアップ

**※ 必ずNC3のDB、および、NC3のアップロードファイルをバックアップして、いつでもリストアできるようにしてから実行してください。**

## 移行ツール実行

```
cd NetCommons3のパス/app
./Console/cake Nc2ToNc3 --database NC2のDB名 --prefix NC2のテーブルプレフィックス名 --upload_path NC2のアップロードファイルフォルダーパス --base_url NC2のベースURL --nc3base NC3のベースパス
```
Ex.)
```
cd /var/www/html/nc3/app
./Console/cake Nc2ToNc3 --database nc2421 --prefix nc_ --upload_path /var/www/html/NC2/html/webapp/uploads/ --base_url http://example.com/NC2/html --nc3base /nc3
```

~~CakePHPのMigrationを実行すると、管理画面に「NC2からの移行」メニューが追加され、画面から実行可能になります。~~
~~画面からの実行は別環境のDBへも接続可能です。~~

#### 補足

2017/2/10 nc2_to_nc3_mapsテーブルを修正しました。
map→nc3_id
2/10以前にNc2ToNc3を配置した場合、nc2_to_nc3_mapsを一度削除して、再度[Migrationを実行](#マイグレーション実行)してください。

#### 不具合情報

現在確認されている不具合は、[GithubのNetCommons3リポジトリのissue、zz Nc2ToNc3（移行ツール）ラベル](https://github.com/NetCommons3/NetCommons3/issues?q=is%3Aissue+is%3Aopen+label%3A%22zz+Nc2ToNc3%EF%BC%88%E7%A7%BB%E8%A1%8C%E3%83%84%E3%83%BC%E3%83%AB%EF%BC%89%22)で確認できます。
