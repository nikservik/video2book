# Улучшения

## [x] Редактирование шагов

+ wysiwyg-редактор Trix уже установлен через pnpm
  + Настроены импорты в app.js и в app.css 
  + Добавлен обработчик события `trix-before-initialize` В app.js для добавления кастомных стилей h2 и h3.
- Над контентом справа вверху блок кнопок иконками:
  - в режиме просмотра: 
    - синяя кнопка с иконкой «Редактировать»:
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
        </svg>
  - в режиме редактирования
    - серая кнопка с иконкой «Назад»:
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
    - синяя кнопка с иконкой «Сохранить»:
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
        </svg>
    
- При переключении в режим редактирования включаем панель редактора над текстом. 
  - отключать редактор можно атрибутом `disabled` (`<trix-editor disabled></trix-editor>`)

- В БД в шаг прогона добавляем поле `original`
- При первом сохранении изменений текста копируем изначальный `result` в `original`. После этого сохраняем правки в `result`.
- При любом сохранении правок пишем в лог: {пользователь} изменил текст в шаге {номер шага прогона} в уроке «{урок}» проекта «{проект}»

### Шаблон использования Trix
```html
<input type="hidden" id="content_input" wire:model.defer="content">

<div wire:ignore>
  <trix-toolbar id="content_toolbar">
    <div class="trix-button-row">
      <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
        <button type="button"
                class="trix-button trix-button--icon trix-button--icon-bold"
                data-trix-attribute="bold" data-trix-key="b"
                title="Bold" tabindex="-1">Bold</button>

        <button type="button"
                class="trix-button trix-button--icon trix-button--icon-italic"
                data-trix-attribute="italic" data-trix-key="i"
                title="Italic" tabindex="-1">Italic</button>
      </span>

      <span class="trix-button-group trix-button-group--block-tools" data-trix-button-group="block-tools">
        <button type="button"
                class="trix-button"
                data-trix-attribute="heading2"
                title="H2" tabindex="-1">H2</button>

        <button type="button"
                class="trix-button"
                data-trix-attribute="heading3"
                title="H3" tabindex="-1">H3</button>

        <button type="button"
                class="trix-button trix-button--icon trix-button--icon-bullet-list"
                data-trix-attribute="bullet"
                title="Bullets" tabindex="-1">Bullets</button>

        <button type="button"
                class="trix-button trix-button--icon trix-button--icon-number-list"
                data-trix-attribute="number"
                title="Numbers" tabindex="-1">Numbers</button>

        <!-- вложенность списков -->
        <button type="button"
                class="trix-button trix-button--icon trix-button--icon-decrease-nesting-level"
                data-trix-action="decreaseNestingLevel"
                title="Outdent" tabindex="-1">Outdent</button>

        <button type="button"
                class="trix-button trix-button--icon trix-button--icon-increase-nesting-level"
                data-trix-action="increaseNestingLevel"
                title="Indent" tabindex="-1">Indent</button>
      </span>
    </div>
  </trix-toolbar>

  <trix-editor input="content_input" toolbar="content_toolbar"></trix-editor>
</div>
```
