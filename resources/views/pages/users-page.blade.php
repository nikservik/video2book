<div
    x-data="{
        copied: false,
        showCopied() {
            this.copied = true;
            setTimeout(() => this.copied = false, 1200);
        },
        copyInvite(link) {
            if (!link) {
                return;
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(link)
                    .then(() => this.showCopied())
                    .catch(() => this.copyInviteFallback(link));
                return;
            }

            this.copyInviteFallback(link);
        },
        copyInviteFallback(link) {
            const textarea = document.createElement('textarea');
            textarea.value = link;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                this.showCopied();
            } finally {
                document.body.removeChild(textarea);
            }
        },
    }"
    class="space-y-6"
>
    <div class="mx-2 md:mx-6 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Пользователи</h1>
        <button type="button"
                wire:click="openCreateUserModal"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
            </svg>
            Добавить пользователя
        </button>
    </div>

    @if ($users->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-300">Пока нет пользователей.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-3">
            @foreach ($users as $user)
                <div class="group relative block">
                    @if ($this->canDelete($user))
                        <button type="button"
                                wire:click="openDeleteUserModal({{ $user->id }})"
                                class="absolute top-3 right-3 z-10 text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                                aria-label="Удалить пользователя {{ $user->name }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                            </svg>
                        </button>
                    @endif

                    <button type="button"
                            wire:click="openEditUserModal({{ $user->id }})"
                            class="block w-full text-left">
                        <article class="rounded-lg border border-gray-200 bg-white px-4 md:px-6 py-4 shadow-sm transition group-hover:border-indigo-400 dark:border-white/10 dark:bg-gray-800 dark:group-hover:border-indigo-500/60">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ $user->name }}</h2>
                        <div class="mt-1 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            <p>{{ $user->email }}</p>
                            <p>{{ $this->levelLabel((int) $user->access_level) }}</p>
                        </div>
                        </article>
                    </button>
                </div>
            @endforeach
        </div>

        {{ $users->links('pagination.twui') }}
    @endif

    @if ($showUserModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-user-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeUserModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="saveUser" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $editingUserId === null ? 'Добавить пользователя' : 'Пользователь' }}
                            </h3>
                        </div>

                        <div>
                            <label for="user-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Имя</label>
                            <div class="mt-2 grid grid-cols-1">
                                @error('userName')
                                    <input id="user-name" type="text" wire:model="userName" aria-invalid="true" aria-describedby="user-name-error"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                @else
                                    <input id="user-name" type="text" wire:model="userName"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                                @enderror
                            </div>
                            @error('userName')
                                <p id="user-name-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="user-email" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Email</label>
                            <div class="mt-2 grid grid-cols-1">
                                @error('userEmail')
                                    <input id="user-email" type="email" wire:model="userEmail" aria-invalid="true" aria-describedby="user-email-error"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                @else
                                    <input id="user-email" type="email" wire:model="userEmail"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                                @enderror
                            </div>
                            @error('userEmail')
                                <p id="user-email-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($editingUserId !== null)
                            <div>
                                <label for="user-invite-link" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Ссылка с инвайтом</label>
                                <div class="mt-2 flex items-center gap-2">
                                    <input id="user-invite-link"
                                           x-ref="userInviteLink"
                                           type="text"
                                           readonly
                                           value="{{ $this->editingUserInviteLink }}"
                                           class="block w-full rounded-md bg-gray-50 py-1.5 pr-3 pl-3 text-gray-700 outline-1 -outline-offset-1 outline-gray-300 sm:text-sm/6 dark:bg-white/5 dark:text-gray-200 dark:outline-white/10">
                                    <button type="button"
                                            x-on:click.prevent="copyInvite($refs.userInviteLink?.value)"
                                            class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                        Копировать
                                    </button>
                                </div>
                                <p x-cloak x-show="copied" class="mt-2 text-sm text-green-600 dark:text-green-400">Ссылка скопирована.</p>
                            </div>

                            <div>
                                <button type="button"
                                        wire:click="rotateInviteToken"
                                        class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600">
                                    Новый токен
                                </button>
                            </div>
                        @endif

                        <div class="mt-10 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">
                                Сохранить
                            </button>
                            <button type="button" wire:click="closeUserModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Отменить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeleteUserModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-delete-user-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeDeleteUserModal"></div>

            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10 dark:bg-red-500/20">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"
                                 class="size-6 text-red-600 dark:text-red-400">
                                <path d="M12 9v4.5m0 3h.008v.008H12v-.008ZM10.29 3.86 1.82 18A2.25 2.25 0 0 0 3.75 21.375h16.5A2.25 2.25 0 0 0 22.18 18L13.71 3.86a2.25 2.25 0 0 0-3.42 0Z"
                                      stroke-linecap="round"
                                      stroke-linejoin="round"/>
                            </svg>
                        </div>

                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Удалить пользователя</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Пользователь <span class="font-medium text-gray-700 dark:text-gray-200">{{ $this->deletingUserName }}</span> будет удалён без возможности восстановления.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button"
                                wire:click="deleteUser"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 sm:ml-3 sm:w-auto dark:bg-red-500 dark:shadow-none dark:hover:bg-red-400">
                            Удалить
                        </button>
                        <button type="button"
                                wire:click="closeDeleteUserModal"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Отменить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
