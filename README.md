Nc2ToNc3
==============

Migration plugin to Nc3 from Nc2 for NetComomns3

[![Build Status](https://travis-ci.org/NetCommons3/Nc2ToNc3.svg?branch=master)](https://travis-ci.org/NetCommons3/Nc2ToNc3)
[![Coverage Status](https://img.shields.io/coveralls/NetCommons3/Nc2ToNc3.svg)](https://coveralls.io/github/NetCommons3/Nc2ToNc3)

Nc2ToNc3(移行ツール)は、同一サーバにNC2のデータとNC3サイトを用意して移行するプラグインです。
NC2最新版（2.4.2.1）からNC3最新版に移行します。

### 手順

1. [NC2を最新版にアップデート](#nc2を最新版にアップデート)
1. [NC2の準備](#nc2の準備)
1. [NC3の準備](#nc3の準備)
1. [NC3のバックアップ](#nc3のバックアップ)
1. [移行ツール実行](#移行ツール実行)

#### NC2を最新版にアップデート

NC2が最新版でなかったら、最新版2.4.2.1にアップデートします。

[NC2を最新版2.4.2.1にアップデート（外部リンク）](https://github.com/netcommons/NetCommons2/releases)

#### NC2の準備

現状NC2のDB及びNC2のアップロードファイルを取得して、NC3と同じ環境にDBインポート及びファイル配置をしてください。

#### NC3の準備

NC3を新規インストールします。[NC3インストール（外部リンク）](https://www.netcommons.org/NetCommons3/download#!#frame-83)

NC3を既にインストール済みの場合、最新版にアップデートします。[NC3アップデート（外部リンク）](https://www.netcommons.org/NetCommons3/download#!#frame-156)

Nc2ToNc3は、app/Plugin配下に配置されています。

```
NetCommons3のパス/app/Plugin/Nc2ToNc3
```

#### NC3のバックアップ

**※ 必ずNC3のDB、および、NC3のアップロードファイルをバックアップして、いつでもリストアできるようにしてから実行してください。**

## 移行ツール実行

```
cd NetCommons3のパス/app
./Console/cake Nc2ToNc3 --database NC2のDB名 --prefix NC2のテーブル名のprefix --upload_path NC2でアップロードしたファイルがあるディレクトリ --base_url NC2のベースURL --nc3base NC3のベースパス
```

**オプション**

|               | 意味                                                |値の例
|---------------| --------------------------------------------------- | ------
|--database     |NC2のDB名                                            |nc2421
|--prefix       |NC2の（DB内の）テーブル名のprefix                      |nc_
|--upload_path  |NC2でアップロードしたファイルがあるディレクトリ          |/var/www/html/nc2/html/webapp/uploads/
|--base_url     |NC2のベースURL                                        |http://example.com/nc2/html
|--nc3base      |NC3のベースパス（ドキュメントルートからの相対パス）      | /nc3

### 例)

**環境例**

|         | URL                         |DB名    |prefix   |ドキュメントルート
|---------| --------------------------- | ------ | ------- | ------
|NC2      |http://example.com/nc2/html  |nc2421  |nc       |/var/www/html/nc2/
|NC3      |http://example.com/nc3       |nc3	 |なし     |/var/www/html/nc3/

**コマンド例**

```
cd /var/www/html/nc3/app
./Console/cake Nc2ToNc3 --database nc2421 --prefix nc_ --upload_path /var/www/html/nc2/html/webapp/uploads/ --base_url http://example.com/nc2/html --nc3base /nc3
```

#### 不具合情報

現在確認されている不具合は、[GithubのNetCommons3リポジトリのissue、zz Nc2ToNc3（移行ツール）ラベル](https://github.com/NetCommons3/NetCommons3/issues?q=is%3Aissue+is%3Aopen+label%3A%22zz+Nc2ToNc3%EF%BC%88%E7%A7%BB%E8%A1%8C%E3%83%84%E3%83%BC%E3%83%AB%EF%BC%89%22)で確認できます。

#### ドキュメント

[データ対応表.pdf](https://github.com/NetCommons3/NetCommons3Docs/blob/gh-pages/NC2toNC3/%E3%83%87%E3%83%BC%E3%82%BF%E5%AF%BE%E5%BF%9C%E8%A1%A8.pdf)
