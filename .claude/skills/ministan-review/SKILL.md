---
name: ministan-review
description: Run the multi-lens review battery on the ministan book (target: book/ja/). Spawns independent-context reviewer subagents — reproducibility (a no-type-theory PHP reader implements from the prose and is graded), type-theory expert, technical-book editor, domain author = Matt Brown / muglug (creator of Psalm, the rival PHP static analyzer; now on Slack's Hakana) judging concept framing, the pragmatic "headlights" view of type safety, the legacy-as-fear redefinition, tooling-first global inference, and citation fairness — NEVER complaining that ministan lacks some Psalm feature, a cross-language type-systems authority = Yusuke Endoh / mametter (author of a TypeScript type-checker book 『型システムのしくみ』, a TAPL translator, developer of TypeProf — Ruby's whole-program inference tool) who contrasts declaration-driven checking with TypeProf-style annotation-free inference, judges cross-language type-theory soundness and teaching design from having written the same genre of book in another language, and never demands PHP become Ruby/TS, Japanese copyeditor, PHPStan-fidelity (claims about "real PHPStan" checked against phpstan-src), plus a READER POOL of PHP/Laravel personas spanning a spectrum (an active PHPStan power user who knows assertType/dumpType, a passive PHPStan user who just follows warnings, and a Laravel dev who writes type declarations but has never heard of static analyzers) and two external static-typing readers (TypeScript, Java/C#) — pick 2–3 per run; a book critic (broad generalist who prizes narrative over info-dumps and rewards woven-in background), and a harsh/cynical counterpart critic (same attributes, reads adversarially to skewer over-claiming, padding, name-dropping, dead metaphors, broken promises). Lenses run in 4 ordered layers — 真 truth (type-theory / PHPStan-fidelity / Psalm-author) → 伝 teaching (reproducibility / PHP+Laravel reader pool) → 読 reading-balance (book critic + harsh critic) → 整 polish (editor + copyeditor) — parallel within a layer, sequential across, editor/copyedit always last. Records each lens to a book/ja/.reviews/_<lens>-review.md note, then synthesizes only the necessary, axis-preserving fixes. Use when asked to "review ministan", "run the review lenses", "査読して/校閲して", validate a chapter or volume before a milestone, or check the book's faithfulness to real PHPStan. Not for tiny single edits.
---

# ministan-review — 多観点レビュー・バッテリー

ministan（PHPStan のエッセンスを最小核に蒸留した静的解析器を、nikic/php-parser を基盤に
一から作る二部本）の本文を、**独立コンテキストのサブエージェント**で複数の専門レンズから
査読し、各結果をノートに記録して、**軸を保った必要な修正だけ**を選択適用するための手順を
凍結したスキル。

このスキルは「品質ゲートの方法論」そのものです。個々の編集ではなく、**章/部の節目**で回す。

## いつ使う / 使わない

- **使う**：章や部を一区切りまで書いた／公開前点検／「実 PHPStan と乖離していないか」確認／
  ユーザーが「査読して」「校閲して」「review ministan」。
- **使わない**：一文の手直しや明白な typo。レンズ起動のオーバーヘッドに見合わない。

## レンズ × 4 レイヤー

レンズを**役割ごとに 4 レイヤー**に束ねる。**レイヤー内は並列・レイヤー間は順次**で回す
（全レンズを一斉に投げない＝それが本スキルの肝）。各レンズは**独立した新規コンテキストの
サブエージェント**（Agent ツール、`general-purpose`）。`opus` を既定（再現性・読者レンズは
`sonnet` 可）。

**実行順：真 → 伝 → 読 → 整。** 前のレイヤーで出た修正を*適用してから*次へ進む（後の
レイヤーは「直ったテキスト」を読む）。理由＝**正しくない記述を磨いても、伝わらない教材を
整えても、無駄**。チュートリアル＝「知識を伝える媒体」としての完成度は、この順でしか
積み上がらない。

| レイヤー | 問い | レンズ | いつ回すか |
|---|---|---|---|
| **L1 真**（正しさ・事実整合・公正） | 書いてあることは**正しいか／世界と整合するか** | ② 型理論・⑥ PHPStan フィデリティ・④ ドメイン著者(Psalm/Matt Brown)・⑪ 別言語×型システム(mametter) | 技術的内容を書いた／変えた直後・新章 |
| **L2 伝**（知識が伝わるか＝教材の核） | 読者は本文だけで**作れるか／理解が繋がるか** | ① 再現性 ＋ 読者プール（PHP/Laravel スペクトラム＋外部）から 2〜3 | 章の初稿ができた／構成を変えた |
| **L3 読**（読み味・分量バランス） | 読み物として**痩せすぎ／太りすぎでないか** | ⑨ 書評家・⑩ 辛口書評家 | 大きな改稿の後・節目 |
| **L4 整**（仕上げ・整合性・公開） | 改稿後の**整合・言語・体裁**は整ったか | ③ 技術書編集者・⑤ 日本語校正・校閲 | 改稿が落ち着いた後・公開前（**最後**） |

- **L1 真** ＝ 本書が*外の世界*（型理論・実 PHPStan・Psalm/型システム文献）について嘘を
  ついていないか。正確さ・乖離・引用の公正さで「事実」を固める。**磨く前に、まず正す。**
- **L2 伝** ＝ **このスキルの心臓**。① 再現性は「本文だけで実装でき、挙動が一致するか」を
  *採点で*測る最強の伝達テスト。読者プールは**多層の代理読者**で「どこで理解が切れたか」を
  *定性的に*補う。**① が量、読者プールが質。**
- **L3 読** ＝ 非専門家が全体を通読し、**痩せすぎ（説明不足・羅列）／太りすぎ（冗長・衒学・
  脱線）**を見抜く。⑨ と ⑩ は同属性の表裏 ― ⑨ が「効いている所」、⑩ が「盛り・はったり・
  約束違反」を担う。
- **L4 整** ＝ **改稿後の整合性チェック**。内容と分量が固まった*後*に、構成・学習設計（③）と
  日本語の質（⑤）を仕上げる。テキストが動くたび校正し直す無駄を避けるため、**必ず最後**。

| # | レンズ | レイヤー | 主眼 | 出力ノート |
|---|---|---|---|---|
| 1 | 再現性（reproducibility） | **L2 伝** | 型知識ゼロの PHP 読者が本文だけで再実装できるか＋挙動採点 | （実装は `/tmp`、所見は本文へ） |
| 2 | 型理論エキスパート | **L1 真** | 形式的・技術的正確さ | `book/ja/.reviews/_expert-review.md` |
| 3 | 技術書編集者 | **L4 整** | 構成・学習設計・公開完成度 | `book/ja/.reviews/_editorial-review.md` |
| 4 | ドメイン著者＝Matt Brown（Psalm 作者） | **L1 真** | 概念の枠組み・実用主義哲学との整合・帰属の公正さ | `book/ja/.reviews/_psalm-review.md` |
| 5 | 日本語校正・校閲 | **L4 整** | 言語の質（慣用句誤用・AI 調・表記ゆれ） | `book/ja/.reviews/_copyedit-review.md` |
| 6 | PHPStan フィデリティ | **L1 真** | 「実 PHPStan では…」の記述が実態と一致するか | `book/ja/.reviews/_fidelity-review.md` |
| 7 | 読者プール（下記 5 ペルソナから選択） | **L2 伝** | 各層の PHP/Laravel 読者・外部読者に飛躍がないか | `book/ja/.reviews/_reader-<persona>-review.md` |
| 9 | 書評家（読み物としての質） | **L3 読** | 散文・背景の厚み・関連情報の織り込み | `book/ja/.reviews/_book-review.md` |
| 10 | 辛口書評家（皮肉屋・批判的読み） | **L3 読** | #9 と同属性で逆張り。誇張・空疎・はったり・約束違反を抉る | `book/ja/.reviews/_harsh-review.md` |
| 11 | 別言語×型システムの専門家＝mametter | **L1 真** | 推論ベース(TypeProf)対比・cross-language の型理論正しさ・同ジャンル著者の教材設計眼 | `book/ja/.reviews/_mametter-review.md` |

### 共通の約束（全レンズに必ず渡す）
- **本書の軸を壊さない**：
  - **non-rejecting**（構文エラーでないコードは受理／分からないことには黙る／`mixed` に縮退）。
    「もっと網羅的に全部検出せよ」「偽陽性を恐れず厳しく」は**禁じ手**（軸の否定）。
  - やさしい基礎編（Part 0–9）／応用編（Seasoned S1–）の二部構成。
  - **PHPStan が本物の副読本**＝ministan は「芯を最小核に蒸留した教材」。
    「PHPStan 並みの網羅性／精度を足せ」ではない。**意図的な簡略化は欠陥ではない**。
  - **dogfood（自己解析が通る）**が品質の柱。
  - TAPL・型システム文献は任意の副読本（数式の厳密さを足せ、ではない）。
- **重大度を付ける**（ERROR / MISLEADING / 表記 / nitpick 等）。瑣末は末尾に少数。
- **読む対象**：基礎編 `book/ja/00-overview.md`〜`09-tooling.md`、
  応用編 `book/ja/seasoned/01-*`〜（依頼に応じて）。`README.md` 等の総説があれば併せて。
  `_*.md`（`.reviews/` 内の内部メモ）は読まない。
- **「答え」を開かない（再現性・読者レンズ）**：本文は `dev/src/...` 等の実装を多数リンク
  するが、再現性・読者レンズは**本文（散文）だけ**を読み、`dev/`・`dev/tests/`・`examples/`・
  `stubs/` は開かない（答えなので実験・読解が無効化する）。事実検証系レンズ（②⑥）は参照可。
- 出力は**最後のメッセージに、観点ごとの表（該当箇所の引用 / 問題 / 修正案）＋総評**。

### レンズ 1：再現性（reader-reproduction）〔L2 伝〕
- ペルソナ：**型理論の知識ゼロ・PHP 中級者**（型宣言は書けるが静的解析器の作り方は知らない）。
  本文だけ（`book/ja/`）を Part 0 から順に読み、`/tmp/ministan-repro-<id>/` に実装。
  **`dev/`・`dev/tests/`・`examples/`・`stubs/` は開かない**（答えなので実験が無効化する）。
  本文が `dev/src/...` をリンクしていても**辿らない**。
- PHP 実行：自前の `/tmp/ministan-repro-<id>/` で `composer require nikic/php-parser`
  （PHPDoc 章以降は `phpstan/phpdoc-parser`、設定章は `nette/neon`）し、
  `php /tmp/ministan-repro-<id>/bin/ministan analyse <file>` /
  `php /tmp/ministan-repro-<id>/bin/ministan annotate <file>` を回す。`php`（8.3+）で実行。
- 返すもの：章ごと（clarity・推測した所・本文の期待出力との食い違い）＋公開 API/CLI の形。
- **採点**：返ってきた実装に対し、シェイプ非依存の挙動採点器を回す（下記「採点ハーネス」）。
  目標は「推測ほぼゼロで本文だけから再実装でき、挙動が一致」。複数名（2〜3）回すと共通の
  詰まり＝本物の穴が出る。

### レンズ 2：型理論エキスパート〔L1 真〕
- ペルソナ：TAPL を教えられる水準（gradual／双方向／変性／部分型／union・narrowing／単一化／
  健全性／ジェネリクス）。
- 事実確認のため `dev/src/`・実 PHPStan（`/Users/megurine/repo/php/phpstan-src`、read-only）を
  参照してよい。
- 探す：形式的な ERROR、条件付きでしか正しくない MISLEADING、文献参照番号の誤り、
  内部/実装との不整合。**honest な簡略化は咎めない**（本書は最小核の教材）。

### レンズ 3：技術書編集者〔L4 整〕
- ペルソナ：中級技術書を多数担当したプロ編集者（**技術者ではない**）。構成・学習曲線・章配分・
  語り・読者適合・公開完成度（まえがき/前提/環境/演習/図/索引/まとめ）を見る。
- 技術的正確さと再現性は別レンズ済みと伝え、**編集観点に集中**させる。各章の「実演」コンソール例や
  「まとめ／簡略化」は足場と理解した上で「最終稿でどう整理すべきか」を述べさせる。

### レンズ 4：ドメイン著者＝Matt Brown / muglug（Psalm 作者）〔L1 真〕
- ペルソナ：**Matt Brown（@muglug）**＝静的解析型チェッカー **Psalm の作者**。ロンドン生まれ・
  ブルックリン在住。**元・聖歌隊の歌手**から転身し、初期は **C# でエンタープライズ開発**（ジェネリクス
  と型システムの設計の楽しさに触れる）。**2014 年 Vimeo** 参画 ―― 巨大で「マジック」に満ちた PHP
  コードベース（毎時数百万リクエスト、変更が怖くて誰も既存メソッドを直さず継ぎ足す悪循環）と、
  自分のコードのバグを本番前に捕まえたいという動機から社内ツールを作り、それが **Psalm**（〜2016
  OSS 化）になった。**2021 年 Slack** へ移籍し、Hack 用の超高速チェッカー **Hakana**（Rust 製）を主導。
  **実用主義・tooling-first** の人。本書がモデルにする PHPStan とは**別系統の設計者**の目で見る
  （TypeProf↔Rigor を **Psalm↔PHPStan** に置き換えた役回り）。
- **彼の哲学（プロンプトに必ず渡す。これで突く）**：
  - **ヘッドライト理論**（"PHP or Type Safety: Pick any two"）：型安全性とは、学術的な「型による
    プログラムエラーの数学的・絶対的排除」ではなく、**ツール（静的解析・IDE）が本番の型起因バグを
    防ぐ・抑制できる能力**。ヘッドライトはエンジンを速くしないが、暗闇の障害物を照らし衝突を防ぐ。
    動的言語に型を付ける意義はこれ。
  - **レガシーの再定義**（"It's not legacy code — it's PHP"）：古さ≠レガシー。**開発者が「既存コードに
    触れるのを恐れ、直さず継ぎ足し始めた瞬間」**にレガシー化する。15 年前の PHP でも、(1) 今も仕事を
    する (2) 静的解析でき型シグネチャで検証できる (3) テストで守られている (4) イディオマティック ――
    なら現役の資産。**CI に静的解析を組み込み「変更の恐怖」を取り除く**ことが核心。
  - **tooling-first／グローバル推論**（"Immutability and beyond"）：人間は**ローカルな推論は得意だが、
    システム全体の副作用伝播（グローバル）を追うのは苦手**。型チェッカーはそこで圧倒的に強い。
    振る舞いを型シグネチャでカプセル化し、規約を**ツールに機械的に強制させる**（不変性・純粋性）。
  - **baseline はレガシー導入のため**（Erik Booij の提案を統合）：既存の数千エラーを「grandfather」し、
    **新規/変更コードだけ**を厳格に見る。初日から恩恵を受けられる。
  - **docblock 擬似ジェネリクス**（"Uncovering PHP bugs with @template"）：C# のジェネリクス経験から、
    **PHP の構文を変えずに** `@template` で厳密な型情報を持ち込んだ（Psalm の歴史的功績）。
  - taint 解析（SQLi/XSS）など「現場の安全」を包括するツール思想。
- **突く角度（framing・帰属・哲学の整合だけ。機能パリティではない）**：
  - 本書の型安全性の語りが**ヘッドライト的（実用主義）**か。**non-rejecting**（働くコードを脅かさない・
    分からないことには黙る）は Brown の「変更の恐怖を取り除く」と深く響き合う ―― そこを正しく/魅力的に
    描けているか、それとも学術的厳密さに偏っていないか。
  - 「**型推論器/推論**」という語法：本書が実際にやること（**宣言/PHPDoc から型を取り、call-site
    引数の本格推論はしない**）を**過大主張していないか**。ローカル vs グローバル推論の整理。
  - **baseline** を本書が「レガシー導入＝恐怖の除去」という*本来の動機*で描けているか、出自（Psalm が
    広め、Booij が提案）の**帰属が公正**か。
  - **PHPDoc ジェネリクス**（`@template`）の語りが、Psalm の先行・功績を不当に消して **PHPStan に
    帰属を偏らせて**いないか。
  - 系譜（Hack/HHVM→Psalm→PHPStan）の引用が**衒学に終わらず読者の益**になっているか。
- **禁じ手（重要）**：**「Psalm にある `@psalm-pure`/`@psalm-immutable`/taint/… が ministan に無い」式の
  機能欠如での難癖は*しない*。** 本書は **PHPStan のエッセンスを蒸留した最小核の教材**であり、
  機能パリティは目的ではない。Brown 自身、機能の数ではなく**「ツールが恐怖を取り除けるか」という
  実用主義**で語る人物。突くのは**概念の枠組み・語法・帰属の公正さ・実用主義哲学との整合**だけ。
- **辛口可**。興味を引く点も率直に。事実確認に Psalm のドキュメント（psalm.dev：上記記事）や
  `/Users/megurine/repo/php/phpstan-src`（read-only）を参照してよい。出力 `book/ja/.reviews/_psalm-review.md`。

### レンズ 5：日本語校正・校閲〔L4 整〕
- ペルソナ：**日本語に堪能なプロの校正・校閲者（技術者ではない）**。日本語の適否だけ。
- 較正例（必ず渡す）：AI 調で軽すぎる断定／意味不明な比喩／自動詞・他動詞の誤用／表記ゆれ。
- **やさしくカジュアルな文体は壊さない**（堅くしない）。**定着した技術表現**（「例外を投げる」
  「`mixed` に倒す／縮退させる」「diff を取る」「型を絞り込む（narrowing）」「dogfood」
  「自己解析が通る」等）は**指摘しない**。技術用語の当否には踏み込まず、迷えば留保。

### レンズ 6：PHPStan フィデリティ（乖離防止）〔L1 真〕
- ペルソナ：実 PHPStan の実装と本書の両方を読み、**「実 PHPStan では…／PHPStan の中では…」と
  いう*事実記述*が実態と一致するか**だけを検証する査読者。
- 読む：本書の「PHPStan に対応」「PHPStan の中では」段 ＋ PHPStan チェックアウト
  **`/Users/megurine/repo/php/phpstan-src`**（read-only）の一次情報：
  - `src/Analyser/`（`Scope`, `NodeScopeResolver`, `TypeSpecifier`）／`src/Type/`／
    `src/Rules/`／`src/Reflection/`／`conf/config.levelN.neon`（レベル定義）。
- **意図的な簡略化は乖離ではない**（各章「まとめ／簡略化／見送り」に記録済み）。直すのは
  *事故的な不正確さ*（実 PHPStan が X なのに本書が ¬X と断言）だけ。両者を区別して報告。
- PHPStan リポジトリは**絶対に編集しない**。

### レンズ 7：読者プール（PHP/Laravel スペクトラム＋外部）〔L2 伝〕
本書の最大の読者層は PHP/Laravel の実務者で、**静的解析との距離が層ごとに大きく違う**。
下の 5 ペルソナを**プール**とし、**各回 2〜3 名**を対象章に合わせて選んで並列起動する
（全員一斉ではない）。共通のやり方：本文だけ（`book/ja/` を Part 0 から順。応用編は依頼が
あれば*軽く*）を読み、`dev/`・`dev/tests/`・`examples/` は開かず、**実装はしない**（再現性
レンズとの違い）。各章で「ここで詰まった／前提が飛んだ／自分の常識と食い違って混乱した」を
**読者の生の声**で記録する。重大度（**BLOCK**＝その層が詰まり先へ進めない／**FRICTION**＝
引っかかるが文脈で回復可能／nitpick）を付ける。出力 `book/ja/.reviews/_reader-<persona>-review.md`。

- **7a. PHPStan パワーユーザー（能動派）**：`assertType()` / `dumpType()` / `dumpPhpDocType()` を
  日常的に使い、レベル・baseline・カスタムルール・PHPDoc ジェネリクス（`array<int,string>`,
  `@template`）を使いこなす。**ツールは深く知るが内部実装・型理論は未学習**。本書を「PHPStan の
  中身を知る」目的で読む。探す：本書の内部モデルが**自分が観測している PHPStan の挙動と食い違う**
  箇所、パワーユーザーでも繋がらない論理の飛躍、`assertType` で確かめた直感と本書の説明のズレ。
- **7b. 受動的 PHPStan ユーザー**：プロジェクトに PHPStan が入っていて**警告になんとなく従っている**
  層。`assertType`/レベルの意味/`@var` の使いどころは曖昧。探す：本書が**能動的な理解を既知扱い**
  して話を進める箇所、「なぜ緑になるのか」を地の文で繋いでいない所、ツール用語（level, baseline,
  ignore）を断りなく使う飛躍。
- **7c. 型宣言のみ・静的解析ツール未知の Laravel 開発者**（いちばん地面に近い主要読者）：
  Laravel でアプリを自由に作れる。`int $x` / `: string` / typed property / enum / readonly は**書く**が、
  **PHPStan や Psalm のような静的解析ツールの存在を知らない**。Eloquent の magic
  （`Model::find()` の `?Model`、動的プロパティ `$user->name`、facade、`Collection`、`mixed` 多用）が
  日常。素朴な像：「型を*書く*と PHP が守ってくれる（？）」。探す：本書が（a）「静的解析という*行為・
  ツール*」を既知として進める、（b）`$user->name` のような**Laravel の magic を解析器が追えない理由**を
  断りなく前提化、（c）「型を*書く*」と「型を*検査する*」のズレを橋渡ししていない箇所。
  **過剰な平易化はしない**：直すべきは*この層が本当に詰まって先へ進めない*真の段差だけ。
- **7d. TypeScript 経験者（外部の静的型視点）**：union・narrowing・構造的型・ジェネリクスの直感を
  TS で持つ。探す：TS の直感が **PHP で裏切られる**箇所（PHP は基本 nominal、構造的型なし、
  ジェネリクスは PHPDoc のみで実行時に無い、コンパイル段が無い、`mixed` ≠ `any` の機微）を
  本書が橋渡ししているか。TS 構文を足場に出す所が**逆に迷わせて**いないか。
- **7e. Java/C# 経験者（外部の静的型視点）**：クラスベース静的型・実行時ジェネリクス（Java は消去）・
  nominal の素養。探す：クラスベースの直感が **PHP で崩れる**箇所（PHP の union 型は言語機能、
  ジェネリクスは doc のみ、`instanceof` narrowing、`mixed`、型消去の有無）を本書が繋いでいるか。

### レンズ 9：書評家（読み物としての質・背景の厚み）〔L3 読〕
- ペルソナ：**特定技術の専門家ではないが、知識と好奇心が極めて幅広い書評家**。分野を横断して
  商業技術書を濫読し、「本として読ませるか」を見抜く。**情報の羅列より散文（ナラティブ）を
  重んじ**、本筋の技術の**背景・歴史・関連分野へのつながり**が明確だと高く評価する。
- 主眼：本書が**読み物として成立しているか**。(a) 各概念に**背景・由来・関連情報**（なぜ PHP で
  静的解析か、Hack/HHVM→Psalm→PHPStan の系譜、PHP 型システムの進化＝型ヒント→スカラー型→
  nullable/union/readonly/enum、gradual の思想史、TAPL/型理論との関係…）が*本筋に織り込まれて*
  説明されているか、(b) 箇条書き・表・コードの**羅列に散文の接続線**が通っているか、(c) 章を
  またいだ**物語の弧**（伏線と回収＝dogfood が宿題を出す等、動機づけ）があるか。**高評価の
  好例も積極的に挙げる**。
- やり方：本文だけを一読者として通読（`dev/`・`examples/` は開かない、技術的検証はしない）。
- **軸を壊さない（重要）**：背景評価は**冗長・脱線の推奨ではない**。やさしい本筋を乱す長広舌や
  初学者を脅かす深掘りは**逆に減点**。深い背景を応用編・脚注に逃がす設計は尊重し、
  「*本筋に最小限の背景が気持ちよく織り込まれているか*」で評価する。non-rejecting の軸は保つ。
- 重大度（**高評価**／**物足りない**／**羅列的**／nitpick）。出力 `book/ja/.reviews/_book-review.md`。

### レンズ 10：辛口書評家（皮肉屋・いじわる・批判的読み）〔L3 読〕
- ペルソナ：**レンズ 9 と同じ属性**（博覧強記・散文重視の書評家）。**ただし性格が逆**――皮肉屋で
  いじわる、何でも疑ってかかる辛口。本を*粗探しするために*読む。自己陶酔・はったり・水増し・
  身内ウケを嫌い、「上手いこと言ったつもり」の比喩や、まえがきの約束と中身の落差を見逃さない。
  レンズ 9 が褒めたものをわざと裏返して読む**対の存在（adversarial counterweight）**。
- 主眼：(a) **誇張・空疎な断定**（「ほぼ全部」「これが核心」式の自賛・盛りすぎ）、(b) **はったり・
  名前落とし**（PHPStan/Psalm/論文/系譜の参照が*衒学*に終わり読者の益になっていない所）、
  (c) **比喩の不発・滑り**、(d) **約束違反**（まえがき「やさしい」「最小核」「自己解析が通る」等と
  本文の実態のズレ）、(e) **水増し・自己満足**（コラム/前方参照/「簡略化／見送り」が読者でなく
  著者のための饒舌になっている所）、(f) **馴れ馴れしさ・上から目線**。具体的な引用で刺す。
- **辛口だが正直であれ（重要）**：価値は「意地悪なふりをした正確さ」。**真の弱点と、単なる好み・
  難癖を区別**して各項に明記（「本当に弱い」か「自分が意地悪なだけ」か）。**軸は壊さない**：
  やさしくカジュアル・non-rejecting・最小核・「PHPStan 並みの網羅性は求めない」は前提。
  **「もっと厳密に／網羅的に／硬く」は禁じ手**（それは辛口ではなく的外れ）。
- 重大度（**痛烈**／**減点**／**いやみ**／nitpick）。各項に「真の弱点／ただの難癖」のラベル。
  出力 `book/ja/.reviews/_harsh-review.md`。

### レンズ 11：別言語×型システムの専門家＝mametter（遠藤侑介）〔L1 真〕
- ペルソナ：**遠藤侑介（@mametter）**＝Ruby コミッター。**『型システムのしくみ ― TypeScript で
  実装しながら学ぶ型とプログラミング言語』の著者**（＝ministan と*同じジャンル*の本を*別言語*で
  書いた人）。**TAPL（Types and Programming Languages）日本語版の訳者の一人**。**TypeProf**（Ruby に
  同梱される、**注釈なしで全体を推論する**型解析ツール）開発者。RBS（Ruby の外部型シグネチャ）
  エコシステムにも通じ、難解 Ruby（quine 等）でも知られる。**角度＝「PHP の外に立つ、別言語と
  型システムの専門家」**。④ Matt Brown が *PHP 内の別ツール作者*なのに対し、⑪ は *PHP の外から
  来た、同ジャンルの本の著者かつ推論ツールの作者*。② 型理論エキスパートを、**cross-language の
  視座と「同じ本を別言語で書いた経験」「本物の推論器を作った経験」**で補完する。
- **渡す対比軸（これで突く）**：
  - **TypeProf（推論ベース・注釈ゼロで全体推論）↔ PHPStan/ministan（宣言・PHPDoc 駆動＋局所推論）。**
    最も鋭い対比。本書の「**推論器／型推論**」という語法を、*本物の推論器*（TypeProf）を作った人の目で
    検める ―― ministan が実際にやるのは「宣言/PHPDoc から型を取り、限定的に局所推論する」検査器で
    あって、TypeProf 的な大域推論ではない。その差を踏まえ、語法が**過大主張でないか／honest か**。
    （chibirigor の Rigor は推論ベースだった ―― 同じ著者なら、ministan の宣言駆動という*設計選択*を
    理解した上で語法を見られる。）
  - **RBS（外部シグネチャファイル）↔ PHPDoc（インライン注釈）。** 型情報を*どこに*書くか、その
    思想差。本書の PHPDoc の扱いが、外部シグネチャを知る目に**素直に映るか**。
  - **『型システムのしくみ』との対比（同ジャンル著者の教材設計眼）。** TS で型チェッカーを実装する
    教材を書いた経験から、**概念の導入順序・実装の見せ方・「作りながら学ぶ」の段差**が効いているか。
    `Scope`/`Type`/narrowing/union/ジェネリクスを、自分ならどう並べ、どこで詰まらせない工夫をしたか。
  - **cross-language の型理論正しさ。** gradual typing・narrowing・union・部分型・健全性などが、
    Ruby/TS と PHP を**横断して正しく**説明されているか（言語差で破綻していないか）。
- **突く**：型理論の cross-language な ERROR/MISLEADING、「推論」語法の過大主張（TypeProf 作者の目）、
  TAPL/『しくみ』引用の公正さ・有益さ、**同ジャンル著者としての教材設計**の弱点（順序・動機づけ・
  実装の見せ方）。興味を引く点・自分の本より優れた工夫も率直に。**辛口可**。
- **禁じ手（重要）**：**「TypeProf/RBS/Ruby にあるこれが ministan に無い」式の言語差・機能差での難癖は
  *しない*。** ministan は **PHP の宣言駆動の検査器を最小核で作る**教材であり、Ruby 化・推論器化が
  目的ではない。突くのは**型システムの普遍的な正しさ・「推論」語法・教材設計・引用の公正さ**だけ。
  「PHP を Ruby/TS にせよ」「TypeProf のように大域推論にせよ」は的外れ。
- 参照（任意）：TypeProf の挙動、TAPL、自著
  `/Users/megurine/Dropbox/EBook/ラムダノート/型システムのしくみ―TypeScriptで実装しながら学ぶ型とプログラミング言語.pdf`
  （あれば引用の公正さ検証用）、`/Users/megurine/repo/php/phpstan-src`（read-only）。
  出力 `book/ja/.reviews/_mametter-review.md`。

## 採点ハーネス（レンズ 1 用・シェイプ非依存）
再現実装の CLI 形（`analyse`/`annotate` の出力体裁が多少違っても）に依存せず**挙動だけ**を採点。
`php <repro>/bin/ministan analyse <file>` のエラー（メッセージ・件数・終了コード）と、
`php <repro>/bin/ministan annotate <file>` の型文字列を**正規化**して期待値と照合する。
代表項目（各章の核＋偽陽性安全ケース）：
- 核：未定義変数、`var_dump` 検出、リテラル/演算の型推論、`instanceof`/`is_*`/`===null` の narrowing、
  未定義メソッド、メソッド/関数の戻り値推論、`@param`/`@return`/`@var`、引数/戻り値の型不一致、
  レベル別の出し分け（level 0→5→6→9）、配列 shape、ジェネリクス置換。
- **偽陽性安全（non-rejecting）**：`isset($y) ? $y : d` を咎めない／`&&`・`||`・`??` 越しの narrowing／
  スーパーグローバル／by-ref 出力引数 `preg_match(..., $m)` の `$m`／低レベルで `mixed` を素通し／
  `match` の腕での narrowing。
- 採点器は `grade.php`（または同等）に固め、`php grade.php <repro>` で `SCORE: n/N` を出す。
  目標は「本文だけで再現でき満点」。複数名いずれも満点なら、その範囲は本文だけで再現可能。
  ＊本書の現状に合わせて項目を更新してから回す（CLI・メッセージ文言は本文の「実演」と一致させる）。

## 起動 → 統合 → 適用（レイヤー駆動）

**基本は「1 レイヤー単位」で回す。** フルサイクル（真→伝→読→整）は大きな節目だけ。日常は
「いま困っている層」だけを回す（下記「どの層を回すか」）。

1. **スコープを選ぶ**：フルサイクル／単一レイヤー（引数 `layer:真|伝|読|整` または `L1`〜`L4`）／
   単一レンズ（`copyedit` / `fidelity` / `psalm` / `mametter` / `reader-7a` / `book-review` / `harsh-review` 等）。
2. **レイヤーを 1 つ回す**：そのレイヤーのレンズを**同一メッセージで並列起動**。L2 伝は読者プールから
   対象章に合う 2〜3 名を選ぶ。各所見を **`book/ja/.reviews/_<lens>-review.md` にコミット**
   （永続・非同期の台帳）。
3. **そのレイヤー分を統合・選択適用**：
   - **軸を最上位に。すべては反映しない。** ERROR・明確な言語誤用・事実乖離を優先。判断が要る
     もの（簡略化の入れ具合・図・大改稿）は記録して著者裁量へ。
   - 適用は **`git add <個別ファイル>`**（`-A` 禁止。並行セッションの WIP を巻き込まない）。
     本文の例を変えたら、必要に応じて **dogfood（`php dev/bin/ministan analyse dev/src`）と
     `./vendor/bin/phpunit` が緑のまま**か確認（`dev/` 不変なら自明）。
4. **次のレイヤーへゲート**（フルサイクル時）：③で適用した修正を踏まえて次のレイヤーを回す
   ＝後のレイヤーは**直ったテキスト**を読む。順序 **真 → 伝 → 読 → 整** は崩さない。
5. 結果をバックログ（`book/ja/_handoff-state.md` 等）に反映。

### どの層を回すか（早見）
- **技術的な内容を書いた／変えた** → **L1 真**（必要なら続けて L2 伝）。
- **章の初稿・構成変更** → **L2 伝**（対象章に効く読者 2〜3 名を選ぶ）。
- **大きな改稿の後** → **L3 読**（痩せ太りの判定）→ 落ち着いたら **L4 整**。
- **公開前の最終点検** → **L4 整**（節目ならフルサイクル）。
- **一文の手直し・明白な typo** → どの層も回さない（オーバーヘッドに見合わない）。

## 注意
- **レイヤー単位**が既定。全レンズ一斉・フルサイクルは重いので大きな節目だけ。
- **順序（真→伝→読→整）を崩さない**。とくに **L4 整（編集・校閲）は必ず最後** ＝ 改稿で動く
  テキストを何度も校正し直す無駄を避けるため。**正しさ→伝達→読み味→仕上げ**の順でしか
  「知識伝達媒体としての完成度」は積み上がらない。
- `opus` 既定。再現性・読者レンズは `sonnet` でも可（複数名で共通の詰まりを見る）。
- このスキルは**方法論の凍結**であり、ペルソナ文面は各回の対象（章/部）に合わせて差し替えてよい。
  読者プールは固定 5 名に縛られない（例：API 設計者、CI 担当、移行作業者など必要に応じ追加）。
