Nc2ToNc3
==============

Migration plugin to Nc3 from Nc2 for NetComomns3

Nc2ToNc3(移行ツール)は、同一サーバにNC2のデータとNC3サイトを用意して移行するプラグインです。

### 手順

1. [NC2の準備](#nc2の準備)
1. [NC3インストール（外部リンク）](https://www.netcommons.org/NetCommons3/download)
1. [NC3のバックアップ](#nc3のバックアップ)
1. [移行ツール実行](#移行ツール実行)

#### NC2の準備

現状NC2のDB及びNC2のアップロードファイルを取得して、NC3と同じ環境にDBインポート及びファイル配置をしてください。

#### Nc2ToNc3配置

NetCommons3.1.5よりNc2ToNc3(移行ツール)が同梱されるようになったため、  
app/Plugin配下に配置されています。

```
NetCommons3のパス/app/Plugin/Nc2ToNc3
```

#### マイグレーション実行

~~cakeコマンドのパス/cake Migrations.migration run -p Nc2ToNc3  -c master -i master~~  
NetCommons3.1.5よりNc2ToNc3(移行ツール)が同梱されるようになったため、マイグレーション実行は不要になりました。

#### NC3のバックアップ

**※ 必ずNc3のDB、および、NC3のアップロードファイルをバックアップして、いつでもリストアできるようにしてから実行してください。**

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
