<div class="space-y-6"
     x-data="{
        draggingProject: false,
        hoveredFolderId: null,
        canDropToFolder(folderId, isClosed) {
            return this.draggingProject && isClosed
        },
        startProjectDrag(event, projectId, projectName) {
            if (! event.dataTransfer) {
                return
            }

            this.draggingProject = true
            this.hoveredFolderId = null
            event.dataTransfer.setData('text/plain', String(projectId))
            event.dataTransfer.effectAllowed = 'move'

            const ghost = document.createElement('div')
            ghost.className = 'inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-sm text-gray-900 shadow-sm dark:border-white/10 dark:bg-gray-800 dark:text-white'

            const icon = document.createElement('span')
            const sourceIcon = event.currentTarget?.querySelector('svg')
            if (sourceIcon) {
                icon.appendChild(sourceIcon.cloneNode(true))
            }

            const text = document.createElement('span')
            text.textContent = projectName

            ghost.appendChild(icon)
            ghost.appendChild(text)
            document.body.appendChild(ghost)

            event.dataTransfer.setDragImage(ghost, 16, 16)
            requestAnimationFrame(() => ghost.remove())
        },
        endProjectDrag() {
            this.draggingProject = false
            this.hoveredFolderId = null
        },
        markFolderHover(folderId, isClosed) {
            if (! this.canDropToFolder(folderId, isClosed)) {
                return
            }

            this.hoveredFolderId = folderId
        },
        clearFolderHover(folderId) {
            if (this.hoveredFolderId === folderId) {
                this.hoveredFolderId = null
            }
        },
        dropProjectOnFolder(event, folderId, isClosed) {
            if (! isClosed || ! event.dataTransfer) {
                return
            }

            const projectId = Number.parseInt(event.dataTransfer.getData('text/plain'), 10)

            if (Number.isNaN(projectId)) {
                return
            }

            this.$wire.moveProjectToFolder(projectId, folderId)
            this.hoveredFolderId = null
        },
     }">
    <div class="mx-2 md:mx-6 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Проекты</h1>
        <button type="button"
                wire:click="openCreateFolderModal"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
            </svg>
            Добавить папку
        </button>
    </div>

    @if ($folders->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-300">
                Пока нет папок.
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800">
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($folders as $folder)
                    <section wire:key="folder-{{ $folder->id }}">
                    <div wire:click="toggleFolder({{ $folder->id }})"
                         x-on:dragenter.prevent="markFolderHover({{ $folder->id }}, {{ $expandedFolderId !== $folder->id ? 'true' : 'false' }})"
                         x-on:dragover.prevent="if ({{ $expandedFolderId !== $folder->id ? 'true' : 'false' }} && $event.dataTransfer) { $event.dataTransfer.dropEffect = 'move'; markFolderHover({{ $folder->id }}, true) }"
                         x-on:dragleave.self="clearFolderHover({{ $folder->id }})"
                         x-on:drop.prevent="dropProjectOnFolder($event, {{ $folder->id }}, {{ $expandedFolderId !== $folder->id ? 'true' : 'false' }})"
                         x-bind:class="{
                            'bg-indigo-50/60 dark:bg-indigo-500/10': canDropToFolder({{ $folder->id }}, {{ $expandedFolderId !== $folder->id ? 'true' : 'false' }}),
                            'ring-1 ring-indigo-300 dark:ring-indigo-500/40': hoveredFolderId === {{ $folder->id }} && canDropToFolder({{ $folder->id }}, {{ $expandedFolderId !== $folder->id ? 'true' : 'false' }})
                         }"
                         class="flex cursor-pointer items-center gap-3 px-3 py-3 md:px-5">
                        @if ($expandedFolderId === $folder->id)
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-indigo-600 dark:text-indigo-400">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-gray-600 dark:text-gray-300">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                            </svg>
                        @endif
                        <div class="flex-1 flex min-w-0 md:items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $folder->name }}</p>
                                @if ($folder->hidden)
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         fill="none"
                                         viewBox="0 0 24 24"
                                         stroke-width="1.5"
                                         stroke="currentColor"
                                         class="size-5 shrink-0 text-gray-500 dark:text-gray-400"
                                         aria-label="Скрытая папка">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                @endif
                                <p class="hidden mt-0.5 shrink-0 text-sm text-gray-500 dark:text-gray-400 md:block">Проектов: {{ $folder->projects_count }}</p>
                            </div>
                            @if ($expandedFolderId === $folder->id)
                                <div class="flex shrink-0 items-center gap-1.5 md:gap-3" wire:click.stop>
                                    <button type="button"
                                            wire:click="openRenameFolderModal({{ $folder->id }})"
                                            class="hidden md:inline-block text-sm font-medium text-gray-600 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-300 dark:hover:text-white dark:focus-visible:outline-indigo-500">
                                        Изменить
                                    </button>
                                    <button type="button"
                                            wire:click="openRenameFolderModal({{ $folder->id }})"
                                            class="inline-flex md:hidden items-center rounded-lg bg-white px-2.5 py-1.5 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                          <path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" />
                                          <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" />
                                        </svg>
                                    </button>
                                    <button type="button"
                                            wire:click="openCreateProjectModal({{ $folder->id }})"
                                            class="hidden md:inline-flex items-center rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                                        Добавить проект
                                    </button>
                                    <button type="button"
                                            wire:click="openCreateProjectModal({{ $folder->id }})"
                                            class="inline-flex md:hidden items-center rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                          <path d="M2 4.25A2.25 2.25 0 0 1 4.25 2h2.5A2.25 2.25 0 0 1 9 4.25v2.5A2.25 2.25 0 0 1 6.75 9h-2.5A2.25 2.25 0 0 1 2 6.75v-2.5ZM2 13.25A2.25 2.25 0 0 1 4.25 11h2.5A2.25 2.25 0 0 1 9 13.25v2.5A2.25 2.25 0 0 1 6.75 18h-2.5A2.25 2.25 0 0 1 2 15.75v-2.5ZM11 4.25A2.25 2.25 0 0 1 13.25 2h2.5A2.25 2.25 0 0 1 18 4.25v2.5A2.25 2.25 0 0 1 15.75 9h-2.5A2.25 2.25 0 0 1 11 6.75v-2.5ZM15.25 11.75a.75.75 0 0 0-1.5 0v2h-2a.75.75 0 0 0 0 1.5h2v2a.75.75 0 0 0 1.5 0v-2h2a.75.75 0 0 0 0-1.5h-2v-2Z" />
                                        </svg>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($expandedFolderId === $folder->id)
                        <div class="border-t border-gray-200 dark:border-white/10">
                            @if ($folder->projects->isEmpty())
                                <div class="px-4 py-5 text-sm text-gray-500 dark:text-gray-400 md:px-5">
                                    В этой папке пока нет проектов.
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                            @foreach ($folder->projects as $project)
                                                <tr wire:key="folder-{{ $folder->id }}-project-{{ $project->id }}"
                                                    x-on:click="if (! $event.target.closest('[data-project-control]')) { window.location.href = '{{ route('projects.show', $project) }}' }"
                                                    class="cursor-pointer transition hover:bg-gray-50 dark:hover:bg-white/5 bg-gray-100 dark:bg-gray-900/70">
                                                    <td class="w-10 min-w-10 max-w-10 py-2 text-right md:w-17 md:min-w-17 md:max-w-17">
                                                        <button type="button"
                                                                draggable="true"
                                                                data-project-control
                                                                x-on:dragstart="startProjectDrag($event, {{ $project->id }}, @js($project->name))"
                                                                x-on:dragend="endProjectDrag()"
                                                                class="inline-flex align-text-bottom cursor-grab items-center text-gray-400 hover:text-gray-600 active:cursor-grabbing dark:text-gray-500 dark:hover:text-gray-300"
                                                                aria-label="Перетащить проект">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20" class="size-5">
                                                                <circle cx="6" cy="5" r="1.3"/>
                                                                <circle cx="6" cy="10" r="1.3"/>
                                                                <circle cx="6" cy="15" r="1.3"/>
                                                                <circle cx="13" cy="5" r="1.3"/>
                                                                <circle cx="13" cy="10" r="1.3"/>
                                                                <circle cx="13" cy="15" r="1.3"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                    <td class="w-full max-w-0 px-3  py-2 text-sm font-medium text-gray-900 md:w-auto md:max-w-none dark:text-white">
                                                        <p class="truncate">{{ $project->name }}</p>
                                                        <p class="font-normal md:hidden mt-1 leading-tight">
                                                            <span class="truncate inline-block mr-2 text-gray-500 dark:text-gray-400">
                                                                Уроков: {{ $project->lessons_count }}
                                                            </span>
                                                            <span class="truncate inline-block text-gray-500 dark:text-gray-400">
                                                                Длительность: {{ $this->projectDurationLabel($project->settings) }}
                                                            </span>
                                                        </p>
                                                    </td>
                                                    <td class="hidden px-4 py-2 text-sm text-gray-700 md:table-cell dark:text-gray-300">
                                                        Уроков: {{ $project->lessons_count }}
                                                    </td>
                                                    <td class="hidden px-4 py-2 text-sm text-gray-700 md:table-cell dark:text-gray-300">
                                                        Длительность: {{ $this->projectDurationLabel($project->settings) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif
                </section>
                @endforeach
            </div>
        </div>
    @endif

    @if ($showCreateFolderModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-create-folder-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeCreateFolderModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative w-full max-w-full transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="createFolder" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Добавить папку</h3>
                        </div>

                        <div>
                            <label for="folder-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название папки</label>
                            <input id="folder-name"
                                   type="text"
                                   name="folder_name"
                                   wire:model="newFolderName"
                                   class="mt-2 block w-full rounded-md bg-white py-1.5 px-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                            @error('newFolderName')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @include('components.folder-visibility-fields', [
                            'hiddenProperty' => 'newFolderHidden',
                            'visibleForProperty' => 'newFolderVisibleFor',
                            'users' => $folderVisibilityUsers,
                            'lockedUserIds' => $lockedFolderVisibilityUserIds,
                            'idPrefix' => 'new-folder-visibility',
                        ])

                        <div class="mt-10 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">
                                Сохранить
                            </button>
                            <button type="button" wire:click="closeCreateFolderModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Отменить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showRenameFolderModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-rename-folder-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeRenameFolderModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative w-full max-w-full transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="renameFolder" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Изменить папку</h3>
                        </div>

                        <div>
                            <label for="editing-folder-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название папки</label>
                            <input id="editing-folder-name"
                                   type="text"
                                   name="editing_folder_name"
                                   wire:model="editingFolderName"
                                   class="mt-2 block w-full rounded-md bg-white py-1.5 px-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                            @error('editingFolderName')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @include('components.folder-visibility-fields', [
                            'hiddenProperty' => 'editingFolderHidden',
                            'visibleForProperty' => 'editingFolderVisibleFor',
                            'users' => $folderVisibilityUsers,
                            'lockedUserIds' => $lockedFolderVisibilityUserIds,
                            'idPrefix' => 'edit-folder-visibility',
                        ])

                        <div class="mt-10 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">
                                Сохранить
                            </button>
                            <button type="button" wire:click="closeRenameFolderModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Отменить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showCreateProjectModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-create-project-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeCreateProjectModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="createProject" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Добавить проект в «{{ $this->selectedProjectFolderName }}»</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Заполните параметры проекта и при необходимости добавьте список уроков.
                            </p>
                            @error('newProjectFolderId')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="project-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название проекта</label>
                            <div class="mt-2 grid grid-cols-1">
                                @error('newProjectName')
                                    <input id="project-name" type="text" name="project_name" wire:model="newProjectName" aria-invalid="true" aria-describedby="project-name-error"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                @else
                                    <input id="project-name" type="text" name="project_name" wire:model="newProjectName"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                                @enderror
                            </div>
                            @error('newProjectName')
                                <p id="project-name-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="project-referer" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Referer</label>
                            <div class="mt-2 grid grid-cols-1">
                                @error('newProjectReferer')
                                    <input id="project-referer" type="url" name="project_referer" wire:model="newProjectReferer" aria-invalid="true" aria-describedby="project-referer-error"
                                           placeholder="https://www.somesite.com/"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                @else
                                    <input id="project-referer" type="url" name="project_referer" wire:model="newProjectReferer"
                                           placeholder="https://www.somesite.com/"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                                @enderror
                            </div>
                            @error('newProjectReferer')
                                <p id="project-referer-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="project-default-pipeline-version" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Версия шаблона по умолчанию</label>
                            <div class="mt-2" wire:replace>
                                <x-pipeline-version-select
                                    id="project-default-pipeline-version"
                                    name="project_default_pipeline_version"
                                    :value="$newProjectDefaultPipelineVersionId"
                                    wire-model="newProjectDefaultPipelineVersionId"
                                    :selected-label="$this->selectedDefaultPipelineVersionLabel"
                                    :options="$pipelineVersionOptions"
                                    :include-empty-option="true"
                                />
                            </div>
                            @error('newProjectDefaultPipelineVersionId')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="project-lessons-list" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Список уроков</label>
                            <div class="mt-2 grid grid-cols-1">
                                <textarea id="project-lessons-list"
                                          name="project_lessons_list"
                                          wire:model="newProjectLessonsList"
                                          rows="8"
                                          placeholder="Урок 1
https://www.youtube.com/watch?v=...

Урок 2
https://www.youtube.com/watch?v=..."
                                          class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"></textarea>
                            </div>
                            @error('newProjectLessonsList')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-10 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">
                                Сохранить
                            </button>
                            <button type="button" wire:click="closeCreateProjectModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Отменить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
