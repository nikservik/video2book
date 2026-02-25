<?php

namespace Tests\Unit\Support;

use App\Support\StepResultHtmlToMarkdownConverter;
use Tests\TestCase;

class StepResultHtmlToMarkdownConverterTest extends TestCase
{
    public function test_it_keeps_block_boundaries_between_div_and_lists(): void
    {
        $html = <<<'HTML'
<h2>Проверка структуры</h2>
<div>Текст перед маркированным списком.</div>
<ul>
    <li>Пункт 1</li>
    <li>Пункт 2</li>
</ul>
<div><strong>Важные факты:</strong></div>
<ul>
    <li>Факт 1</li>
</ul>
<div>Темы для изучения:</div>
<ol>
    <li>Тема 1</li>
    <li>Тема 2</li>
</ol>
HTML;

        $markdown = app(StepResultHtmlToMarkdownConverter::class)->convert($html);

        $this->assertSame(
            <<<'MD'
## Проверка структуры

Текст перед маркированным списком.

- Пункт 1
- Пункт 2

**Важные факты:**

- Факт 1

Темы для изучения:

1. Тема 1
2. Тема 2
MD,
            trim($markdown)
        );
    }

    public function test_it_converts_nested_lists_and_inline_formatting_inside_items(): void
    {
        $html = <<<'HTML'
<ol>
    <li><strong>Первый</strong> пункт</li>
    <li>Второй пункт
        <ul>
            <li><em>Вложенный</em> элемент</li>
        </ul>
    </li>
</ol>
HTML;

        $markdown = app(StepResultHtmlToMarkdownConverter::class)->convert($html);

        $this->assertSame(
            <<<'MD'
1. **Первый** пункт
2. Второй пункт
    - *Вложенный* элемент
MD,
            trim($markdown)
        );
    }

    public function test_it_keeps_inline_strong_inside_text_without_splitting_into_separate_paragraphs(): void
    {
        $html = 'Мы начинаем серию уроков по <strong>Титхи Правеша</strong> — это особая ведическая система годового гороскопа.';

        $markdown = app(StepResultHtmlToMarkdownConverter::class)->convert($html);

        $this->assertSame(
            'Мы начинаем серию уроков по **Титхи Правеша** — это особая ведическая система годового гороскопа.',
            trim($markdown)
        );
    }

    public function test_it_keeps_list_item_content_in_single_line_when_html_contains_br_inside_li(): void
    {
        $html = <<<'HTML'
<ul>
    <li><strong>Как настраиваемся:</strong><br>Садимся удобно, складываем руки в Намасте.</li>
    <li><strong>Мантра:</strong> Читаем 12 раз мантру «Ом Гураве Нама».</li>
</ul>
HTML;

        $markdown = app(StepResultHtmlToMarkdownConverter::class)->convert($html);

        $this->assertSame(
            <<<'MD'
- **Как настраиваемся:** Садимся удобно, складываем руки в Намасте.
- **Мантра:** Читаем 12 раз мантру «Ом Гураве Нама».
MD,
            trim($markdown)
        );
    }
}
