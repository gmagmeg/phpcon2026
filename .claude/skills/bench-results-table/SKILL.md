---
name: bench-results-table
description: Reflect phpbench measurement results into the comparison tables of this JIT/OPcache presentation (index.html). Use this whenever the user pastes phpbench output (rows like "-- JIT OFF/function/tracing --", benchXxx subjects, a `mode` time column) and asks to update, add, or fix a 計測結果 table, or mentions ARM64/x86_64 measurement values, μs timings, or "この結果を表に". Handles the column mapping, arch handling, rounding, and preview verification so the numbers land in the house style without re-deriving the conventions each time.
---

# Reflecting benchmark results into the presentation tables

This presentation (`index.html`, reveal.js) compares PHP behavior with/without OPcache & JIT. Measurement slides use `<table class="compare-table">`. The user runs phpbench and pastes the raw output; your job is to translate that into a table row set that matches the house conventions below, then verify it in the preview.

The point of these conventions is consistency: every measurement table should read the same way so the audience can compare slides at a glance. Follow them unless the user asks otherwise.

## Input format

The user pastes phpbench output, usually grouped by JIT mode:

```
-- JIT OFF --
| benchmark    | subject      | ... | mode     | rstdev |
| InArrayBench | benchInArray | ... | 12.695μs | ±3.75% |
| InArrayBench | benchIsset   | ... | 0.029μs  | ±4.44% |
-- JIT function --
| InArrayBench | benchInArray | ... | 12.784μs | ...    |
| InArrayBench | benchIsset   | ... | 0.081μs  | ...    |
-- JIT tracing --
| InArrayBench | benchInArray | ... | 12.836μs | ...    |
| InArrayBench | benchIsset   | ... | 0.083μs  | ...    |
```

The number you want is the `mode` column (the representative time). `revs`/`its`/`mem_peak`/`rstdev` are context, not table content.

## Column mapping (JIT modes → OFF / ON)

- **`JIT OFF`** → the **OFF** column.
- **`JIT tracing`** → the **ON** column. Tracing is PHP's default JIT mode, so it represents "JIT ON". `JIT function` is almost always within noise of tracing — don't add a separate column for it unless the user explicitly wants function vs tracing broken out.
- **Column order is OFF then ON** (left to right). This mirrors the "before → after" reading direction the deck uses elsewhere.

Table header for a per-arch comparison:

```html
<thead><tr><th>処理</th><th>アーキ</th><th>OFF</th><th>ON</th><th>ON 比</th></tr></thead>
```

## Architecture handling

Measurements come from two environments and they are **not interchangeable** (see the project memory: x86-premised value-object numbers don't reproduce on ARM64):

- **ARM64** = the older / previously-reflected numbers (Apple Silicon dev box).
- **x86_64** = the latest numbers.

When both exist for a process, keep **both rows** so the arch difference is visible. Put ARM64 first, x86_64 second, and mark the **x86_64 (latest) row with `class="highlight"`** so the current environment stands out. If the user only gives one arch, just update/add that arch's row(s).

## Row formatting

- **Process name**: strip the `bench` prefix and lowercase to the real function name — `benchInArray` → `in_array`, `benchIsset` → `isset`, `benchArrayKeyExists` → `array_key_exists`. Keep an O-notation suffix only if the surrounding table already uses one (e.g. `in_array（O(n)）`); match whatever the current slide does rather than adding it unprompted.
- **Decimal precision**: match the magnitude and the existing rows. Roughly: values ≥10μs → 2 dp (`12.84μs`), sub-μs → 3 dp (`0.083μs`). Keep the `μs` unit.
- **Rounding of raw modes**: `12.836μs` → `12.84μs`. Don't invent precision the source doesn't have.

## The `ON 比` column

This column expresses each row's speed **relative to the `in_array` baseline, measured in the ON column** (that's what "150倍" has always meant on these slides). Compute `in_array_ON / row_ON` and round to a clean figure:

- baseline row (`in_array`) → `基準（ON ≒ OFF）`, or `ー` if you're only showing raw numbers.
- `isset`: `12.84 / 0.083 ≈ 155` → **約150倍速** (round to the nearest clean 10/50, consistent with earlier slides).
- `array_key_exists`: `12.84 / 0.123 ≈ 104` → **約100倍速**.

Keep the ratio anchored to the ON column across arches so ARM64 and x86_64 rows report the same "約150倍速" story.

## Red emphasis for counter-intuitive cells

When a number makes a point that fights intuition — most commonly **JIT ON being *slower* than OFF for tiny internal functions** (isset/array_key_exists) — color that cell red so the audience catches it:

```html
<td style="color:#c00;font-weight:700;">0.083μs</td>
```

Use this sparingly and only on the cell the user wants to call out.

## Worked example (the canonical in_array vs isset slide)

Input: ARM64 (previously in the table) + fresh x86_64 phpbench output, `in_array` and `isset` only.

```html
<table class="compare-table">
    <thead><tr><th>処理</th><th>アーキ</th><th>OFF</th><th>ON</th><th>ON 比</th></tr></thead>
    <tbody>
        <tr><td>in_array</td><td>ARM64</td><td>11.88μs</td><td>11.75μs</td><td>ー</td></tr>
        <tr class="highlight"><td>in_array</td><td>x86_64</td><td>12.70μs</td><td>12.84μs</td><td>ー</td></tr>
        <tr><td>isset</td><td>ARM64</td><td>0.022μs</td><td style="color:#c00;font-weight:700;">0.079μs</td><td>約 150 倍速</td></tr>
        <tr class="highlight"><td>isset</td><td>x86_64</td><td>0.029μs</td><td style="color:#c00;font-weight:700;">0.083μs</td><td>約 150 倍速</td></tr>
    </tbody>
</table>
```

## Editing mechanics

- The target file is `index.html`. Find the right table with `grep -n` on the slide's `<h2>` (e.g. `計測結果：in_array vs isset`) or a distinctive existing value.
- The file uses **tab indentation** and full-width parens `（）`. When an `Edit` fails on whitespace, fall back to editing one `<tr>`/`<td>` at a time — those short unique strings match reliably.
- Don't reorder or restyle unrelated tables.

## Verify in the preview (always)

A dev server is usually already running (`preview_list`). After editing:

1. Navigate to the slide and read back the table so you can confirm the mapping landed, not just that the file saved:
   ```js
   (function(){var s=[...document.querySelectorAll('.slides>section')].find(x=>{var h=x.querySelector('h2');return h&&h.textContent.includes('in_array vs isset');});var i=Reveal.getIndices(s);Reveal.slide(i.h,i.v||0);return JSON.stringify({head:[...s.querySelectorAll('thead th')].map(t=>t.textContent),rows:[...s.querySelectorAll('tbody tr')].map(r=>[...r.children].map(c=>c.textContent).join(' | '))});})()
   ```
   Use `Reveal.getIndices(section)` for navigation — a raw DOM index passed to `Reveal.slide()` can land on the wrong slide when vertical stacks exist.
2. For any red-emphasis cell, confirm the computed color with `getComputedStyle(td).color` (expect `rgb(204, 0, 0)`) rather than trusting the screenshot.
3. Take a `preview_screenshot` of the finished slide to show the user.

## Confirm-with-user checkpoints

These choices genuinely change the output — surface them rather than guessing when the input is ambiguous:

- Which arch(es) to show, and whether to keep prior rows or replace them.
- Whether to include extra subjects the paste contains (e.g. `array_key_exists`) or narrow to the ones named. The user often narrows the table even when the paste has more.
- Whether `ON 比` should be reported (and against which baseline) when the slide isn't the in_array/isset one.
