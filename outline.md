
# 流れ

Opechae、JITの軽い紹介
早速計測

in_array vs isset / array_key_exists（PHPBench・mode）

JITを入れても差が出ない
なぜか
OPECODEの高速化
関数マップの呼び出し先の処理は高速化できない


高速化する部分がほどんどない

bc_math
四則演算
四則演算はOPECODEなのでJITが効く
ネイティブ float 演算子 vs bcmath（PHPBench・mode）


標準関数でJITが効くパターン
③ コールバックを取る標準関数（array_map / usort）— JITは効くか（PHPBench・mode）
コールバック部分でOPECODEが発生

④ 正規表現の「もう一つの JIT」= PCRE JIT（PHPBench・mode）

Opechacheが効くケース
コード量が多いほど効く
フレームワークがまさに典型
比較

逆にフレームワークを利用しない場合は効果薄い

フレームワークを使いつつ
ユーザー手続きコードが多くなる
クリーンアーキテクチャな思想のアーキテクチャと相乗効果が高い

外部依存の層：opecache
コアドメインの層：JIT

