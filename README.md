Nc2ToNc3
==============

Migration plugin to Nc3 from Nc2 for NetComomns3

## 説明

```
cakeコマンドのパス/cake Nc2ToNc3 --database NC2のDB名 --prefix NC2のテーブルプレフィックス名
```

シェルからの実行は、現状NC2のダンプファイルをNC3のDBと同じ環境にインポートして実行してください。

CakePHPのMigrationを実行すると、管理画面に「NC2からの移行」メニューが追加され、画面から実行可能になります。
画面からの実行は別環境のDBへも接続可能です。

開発中につき、必ずNc3のDB、および、NC3のアップロードファイルをバックアップして、いつでもリストアできるようにしてから実行してください。
