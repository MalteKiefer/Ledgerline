<x-layouts.app :title="$contactId ? __('contacts.ui.edit_contact') : __('contacts.ui.new_contact')">
    @php $cfg = [
        'dataUrl' => route('contacts.data'),
        'storeUrl' => route('contacts.store'),
        'contactBase' => url('contacts'),
        'suggestUrl' => route('contacts.suggest'),
        'indexUrl' => route('contacts.index'),
        'galleryPickerUrl' => route('gallery.picker'),
        'peopleUrl' => route('gallery.people.data'),
        'peopleShowBase' => url('gallery/people'),
        'filesDataUrl' => route('files.data'),
        'filesRawBase' => url('files/raw'),
        'token' => csrf_token(),
        'confirmDelete' => __('contacts.ui.delete_confirm'),
        'savedToast' => __('contacts.ui.saved'),
        'contactId' => $contactId,
    ]; @endphp
    <div x-data="contactEditorPage(@js($cfg))" x-init="init()" class="mx-auto max-w-xl">
        <x-page-heading :title="$contactId ? __('contacts.ui.edit_contact') : __('contacts.ui.new_contact')">
            <x-slot:actions>
                <x-button icon="chevron-left" :href="$contactId ? route('contacts.view', $contactId) : route('contacts.index')">{{ __('contacts.ui.back') }}</x-button>
            </x-slot:actions>
        </x-page-heading>

        <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            {{-- Avatar + favorite --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                        <template x-if="form.avatar"><img :src="form.avatar" class="h-full w-full object-cover"></template>
                    </div>
                    <x-button variant="secondary" icon="pencil" class="!px-2 !py-1 !text-xs" x-show="form.id" @click="openAvatarModal()">{{ __('contacts.ui.avatar_change') }}</x-button>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500" x-show="! form.id">{{ __('contacts.ui.avatar_after_save') }}</span>
                </div>
                <button type="button" @click="form.favorite = ! form.favorite"
                    class="inline-flex min-h-9 min-w-9 items-center justify-center text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    :title="form.favorite ? '{{ __('contacts.ui.favorite_remove') }}' : '{{ __('contacts.ui.favorite_add') }}'">
                    <x-icon x-show="! form.favorite" name="star" class="h-5 w-5" />
                    <x-icon x-show="form.favorite" x-cloak name="star-solid" class="h-5 w-5" />
                </button>
            </div>

            <div class="mt-5 space-y-4">
                {{-- Name --}}
                <div class="grid grid-cols-2 gap-2">
                    <input x-model="form.first_name" placeholder="{{ __('contacts.ui.first_name') }}" class="rounded-md border-gray-300 text-sm">
                    <input x-model="form.last_name" placeholder="{{ __('contacts.ui.last_name') }}" class="rounded-md border-gray-300 text-sm">
                </div>

                {{-- Company + job title --}}
                <div class="grid grid-cols-2 gap-2">
                    <input x-model="form.org" placeholder="{{ __('contacts.ui.org') }}" class="rounded-md border-gray-300 text-sm">
                    <input x-model="form.title" placeholder="{{ __('contacts.ui.title') }}" class="rounded-md border-gray-300 text-sm">
                </div>

                {{-- Phones --}}
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.phone') }}</span>
                    <template x-for="(p,i) in form.phones" :key="'p'+i">
                        <div class="mt-1 flex items-center gap-2">
                            <input x-model="form.phones[i].value" placeholder="{{ __('contacts.ui.phone') }}" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                            <select x-model="form.phones[i].type" class="shrink-0 rounded-md border-gray-300 dark:border-gray-700 py-2 text-xs">
                                <option value="cell">{{ __('contacts.ui.label_mobile') }}</option>
                                <option value="home">{{ __('contacts.ui.label_home') }}</option>
                                <option value="work">{{ __('contacts.ui.label_work') }}</option>
                                <option value="other">{{ __('contacts.ui.label_other') }}</option>
                            </select>
                            <button type="button" @click="form.phones.splice(i,1)" class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <button type="button" @click="form.phones.push({value:'',type:'cell'})" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.phone') }}</button>
                </div>

                {{-- Emails --}}
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.email') }}</span>
                    <template x-for="(e,i) in form.emails" :key="'e'+i">
                        <div class="mt-1 flex items-center gap-2">
                            <input x-model="form.emails[i].value" type="email" placeholder="{{ __('contacts.ui.email') }}" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                            <select x-model="form.emails[i].type" class="shrink-0 rounded-md border-gray-300 dark:border-gray-700 py-2 text-xs">
                                <option value="home">{{ __('contacts.ui.label_home') }}</option>
                                <option value="work">{{ __('contacts.ui.label_work') }}</option>
                                <option value="other">{{ __('contacts.ui.label_other') }}</option>
                            </select>
                            <button type="button" @click="form.emails.splice(i,1)" class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <button type="button" @click="form.emails.push({value:'',type:'home'})" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.email') }}</button>
                </div>

                {{-- Websites --}}
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.website') }}</span>
                    <template x-for="(u,i) in form.urls" :key="'u'+i">
                        <div class="mt-1 flex items-center gap-2">
                            <input x-model="form.urls[i].value" type="url" placeholder="https://" class="min-w-0 flex-1 rounded-md border-gray-300 dark:border-gray-700 text-sm">
                            <select x-model="form.urls[i].type" class="shrink-0 rounded-md border-gray-300 dark:border-gray-700 py-2 text-xs">
                                <option value="home">{{ __('contacts.ui.label_home') }}</option>
                                <option value="work">{{ __('contacts.ui.label_work') }}</option>
                                <option value="other">{{ __('contacts.ui.label_other') }}</option>
                            </select>
                            <button type="button" @click="form.urls.splice(i,1)" class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <button type="button" @click="form.urls.push({value:'',type:'home'})" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.website') }}</button>
                </div>

                {{-- Postal addresses --}}
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.addresses') }}</span>
                    <template x-for="(a,i) in form.addresses" :key="'ad'+i">
                        <div class="mt-1 space-y-1 rounded-md border border-gray-200 dark:border-gray-800 p-2">
                            <div class="flex items-center gap-2">
                                <select x-model="form.addresses[i].type" class="rounded-md border-gray-300 dark:border-gray-700 py-1 text-xs">
                                    <option value="home">{{ __('contacts.ui.address_type_home') }}</option>
                                    <option value="work">{{ __('contacts.ui.address_type_work') }}</option>
                                    <option value="other">{{ __('contacts.ui.address_type_other') }}</option>
                                </select>
                                <span class="ml-auto flex items-center gap-1">
                                    <button type="button" x-show="form.id" @click="showMap(i)" class="inline-flex min-h-9 min-w-9 items-center justify-center text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" title="{{ __('contacts.ui.address_map') }}"><x-icon name="map-pin" class="h-4 w-4" /></button>
                                    <button type="button" @click="form.addresses.splice(i,1)" class="inline-flex min-h-9 min-w-9 items-center justify-center text-gray-400 dark:text-gray-500 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                                </span>
                            </div>
                            <input x-model="form.addresses[i].street" placeholder="{{ __('contacts.ui.address_street') }}" class="w-full rounded-md border-gray-300 text-sm">
                            <input x-model="form.addresses[i].ext" placeholder="{{ __('contacts.ui.address_ext') }}" class="w-full rounded-md border-gray-300 text-sm">
                            <div class="grid grid-cols-3 gap-1">
                                <input x-model="form.addresses[i].zip" placeholder="{{ __('contacts.ui.address_zip') }}" class="rounded-md border-gray-300 text-sm">
                                <input x-model="form.addresses[i].city" placeholder="{{ __('contacts.ui.address_city') }}" class="col-span-2 rounded-md border-gray-300 text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-1">
                                <input x-model="form.addresses[i].region" placeholder="{{ __('contacts.ui.address_region') }}" class="rounded-md border-gray-300 text-sm">
                                <input x-model="form.addresses[i].country" placeholder="{{ __('contacts.ui.address_country') }}" class="rounded-md border-gray-300 text-sm">
                            </div>
                        </div>
                    </template>
                    <button type="button" @click="form.addresses.push({type:'home',street:'',ext:'',zip:'',city:'',region:'',country:''})" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.address') }}</button>
                </div>

                {{-- Birthday + anniversaries --}}
                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.bday') }}
                        <input type="date" x-model="form.bday" class="mt-0.5 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm">
                    </label>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.anniversaries') }}</span>
                    <template x-for="(a,i) in form.anniversaries" :key="'a'+i">
                        <div class="mt-1 flex items-center gap-2">
                            <input type="date" x-model="form.anniversaries[i].date" class="rounded-md border-gray-300 dark:border-gray-700 text-sm">
                            <input x-model="form.anniversaries[i].label" placeholder="{{ __('contacts.ui.anniversary_label') }}" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                            <button type="button" @click="form.anniversaries.splice(i,1)" class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <button type="button" @click="form.anniversaries.push({date:'',label:''})" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.anniversary') }}</button>
                </div>

                {{-- Related contacts --}}
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.related') }}</span>
                    <template x-for="(r,i) in form.related" :key="'r'+i">
                        <div class="mt-1 flex items-center gap-2">
                            <select x-model="form.related[i].type" class="shrink-0 rounded-md border-gray-300 dark:border-gray-700 py-1 text-xs">
                                @foreach (['spouse','child','parent','sibling','friend','colleague','assistant','manager','other'] as $rt)
                                    <option value="{{ $rt }}">{{ __('contacts.ui.related_type_'.$rt) }}</option>
                                @endforeach
                            </select>
                            <div class="relative min-w-0 flex-1">
                                <input x-model="form.related[i].name" @input.debounce.250ms="relatedSearch(i)" @focus="relatedIndex=i"
                                    placeholder="{{ __('contacts.ui.related_name') }}" class="w-full rounded-md border-gray-300 text-sm"
                                    :class="form.related[i].uid && 'font-medium'">
                                <div x-show="relatedIndex===i && relatedSuggestions.length" x-cloak @click.outside="relatedSuggestions=[]"
                                    class="absolute z-20 mt-1 max-h-40 w-full overflow-auto rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 shadow-lg">
                                    <template x-for="s in relatedSuggestions" :key="s.id">
                                        <button type="button" @click="pickRelated(i, s)" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <span x-text="s.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <button type="button" @click="form.related.splice(i,1)" class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <button type="button" @click="form.related.push({type:'friend',name:'',uid:null})" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.related_add') }}</button>
                </div>

                {{-- Custom labelled fields --}}
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.custom_fields') }}</span>
                    <template x-for="(f,i) in form.custom_fields" :key="'cf'+i">
                        <div class="mt-1 flex items-center gap-2">
                            <input x-model="form.custom_fields[i].label" placeholder="{{ __('contacts.ui.custom_field_label') }}" class="w-1/3 rounded-md border-gray-300 text-sm">
                            <input x-model="form.custom_fields[i].value" placeholder="{{ __('contacts.ui.custom_field_value') }}" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                            <button type="button" @click="form.custom_fields.splice(i,1)" class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <button type="button" @click="form.custom_fields.push({label:'',value:''})" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.custom_field_add') }}</button>
                </div>

                <textarea x-model="form.note" placeholder="{{ __('contacts.ui.note') }}" rows="3" class="w-full rounded-md border-gray-300 text-sm"></textarea>

                {{-- Groups: multi-select with autocomplete --}}
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.groups') }}</span>
                    <div class="mt-1 rounded-md border border-gray-300 dark:border-gray-700 px-2 py-1.5" @click="$refs.groupInput?.focus()">
                        <div class="flex flex-wrap items-center gap-1">
                            <template x-for="gid in (form.group_ids || [])" :key="gid">
                                <span class="inline-flex items-center gap-1 rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-700 dark:text-gray-300">
                                    <span x-text="groupName(gid)"></span>
                                    <button type="button" @click="removeGroupChip(gid)" class="text-gray-400 dark:text-gray-500 hover:text-red-600">&times;</button>
                                </span>
                            </template>
                            <div class="relative min-w-[6rem] flex-1">
                                <input x-ref="groupInput" x-model="groupQuery" @focus="groupOpen=true" @click="groupOpen=true"
                                    @keydown.escape.stop="groupOpen=false" @click.outside="groupOpen=false"
                                    placeholder="{{ __('contacts.ui.group_add_placeholder') }}" class="w-full border-0 p-0 text-sm focus:ring-0">
                                <div x-show="groupOpen && filteredGroups().length" x-cloak
                                    class="absolute z-20 mt-1 max-h-40 w-56 overflow-auto rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 shadow-lg">
                                    <template x-for="g in filteredGroups()" :key="g.id">
                                        <button type="button" @click="addGroupChip(g.id)" class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800" x-text="g.name"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <select x-model="form.book_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 text-sm">
                    <template x-for="b in books.filter(x=>x.owned)" :key="b.id"><option :value="b.id" x-text="b.name"></option></template>
                </select>
            </div>

            <div class="mt-6 flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-4">
                <button x-show="form.id" @click="destroy()" class="text-sm text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">{{ __('contacts.ui.delete') }}</button>
                <div class="ml-auto flex gap-2">
                    <x-button :href="$contactId ? route('contacts.view', $contactId) : route('contacts.index')">{{ __('contacts.ui.cancel') }}</x-button>
                    <x-button variant="primary" x-bind:disabled="saving" @click="save()">{{ __('contacts.ui.save') }}</x-button>
                </div>
            </div>
        </div>

        {{-- Address map preview --}}
        <div x-show="mapModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="closeMap()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeMap()"></div>
            <div class="relative my-16 w-full max-w-xl rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('contacts.ui.map_title') }}</h3>
                    <button type="button" @click="closeMap()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>
                <p x-show="mapModal.error" x-cloak class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('contacts.ui.map_not_found') }}</p>
                <p x-show="mapModal.loading" x-cloak class="mt-4 text-sm text-gray-400 dark:text-gray-500">…</p>
                <div x-show="! mapModal.error && ! mapModal.loading" class="mt-4">
                    <div x-ref="contactMap" class="h-72 w-full overflow-hidden rounded-md ring-1 ring-gray-200 dark:ring-gray-700"></div>
                    <p class="mt-2 truncate text-xs text-gray-500 dark:text-gray-400" x-text="mapModal.display"></p>
                    <a x-show="mapModal.osmUrl" :href="mapModal.osmUrl" target="_blank" rel="noopener"
                        class="mt-1 inline-flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-white">
                        <x-icon name="map-pin" class="h-3.5 w-3.5" />{{ __('contacts.ui.map_open_osm') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Avatar picker + crop (device / gallery / people / files) --}}
        <div x-show="avatarModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="closeAvatarModal()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeAvatarModal()"></div>
            <div class="relative my-10 w-full max-w-2xl rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('contacts.ui.avatar_pick_title') }}</h3>
                    <button type="button" @click="closeAvatarModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>

                {{-- Crop step --}}
                <template x-if="cropSrc">
                    <div class="mt-4">
                        <div class="mx-auto max-h-[52vh] overflow-hidden bg-gray-50 dark:bg-gray-800">
                            <img x-ref="cropImg" :src="cropSrc" alt="" class="block max-w-full">
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.ui.avatar_crop_hint') }}</p>
                        <div class="mt-3 flex justify-end gap-2">
                            <x-button @click="cropSrc=null; destroyCropper()">{{ __('contacts.ui.cancel') }}</x-button>
                            <x-button variant="primary" x-bind:disabled="saving" @click="confirmCrop()">{{ __('contacts.ui.avatar_apply') }}</x-button>
                        </div>
                    </div>
                </template>

                {{-- Source picker --}}
                <div x-show="!cropSrc" class="mt-4">
                    <div class="flex gap-1 border-b border-gray-100 dark:border-gray-800 text-sm">
                        @foreach (['upload','gallery','people','files'] as $t)
                            <button type="button" @click="avatarTab('{{ $t }}')"
                                :class="avatarModal.tab==='{{ $t }}' ? 'border-gray-900 dark:border-gray-100 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                                class="-mb-px border-b-2 px-3 py-2 font-medium">{{ __('contacts.ui.avatar_tab_'.$t) }}</button>
                        @endforeach
                    </div>

                    <div class="mt-4 min-h-[12rem]">
                        <p x-show="avatarModal.loading" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">…</p>

                        {{-- Upload --}}
                        <div x-show="avatarModal.tab==='upload' && !avatarModal.loading">
                            <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700 py-12 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <x-icon name="arrow-up-tray" class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                                <span>{{ __('contacts.ui.avatar_choose_file') }}</span>
                                <input type="file" accept="image/*" class="hidden" @change="pickDeviceImage($event)">
                            </label>
                        </div>

                        {{-- Gallery --}}
                        <div x-show="avatarModal.tab==='gallery' && !avatarModal.loading">
                            <p x-show="!galleryPhotos.length" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('contacts.ui.avatar_no_images') }}</p>
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                <template x-for="p in galleryPhotos" :key="p.id">
                                    <button type="button" @click="startCrop(p.full)" class="aspect-square overflow-hidden rounded-md ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-900">
                                        <img :src="p.thumb" alt="" class="h-full w-full object-cover" loading="lazy">
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- People: pick a person (filter) → choose one of their photos --}}
                        <div x-show="avatarModal.tab==='people' && !avatarModal.loading">
                            <div x-show="!personSelected">
                                <p x-show="!peopleList.length" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('contacts.ui.avatar_no_images') }}</p>
                                <div class="grid grid-cols-4 gap-3 sm:grid-cols-6">
                                    <template x-for="p in peopleList" :key="p.id">
                                        <button type="button" @click="pickPerson(p)" class="text-center">
                                            <span class="block aspect-square overflow-hidden rounded-full ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-900">
                                                <img :src="p.cover" alt="" class="h-full w-full object-cover" loading="lazy">
                                            </span>
                                            <span class="mt-1 block truncate text-xs text-gray-500 dark:text-gray-400" x-text="p.name || ''"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div x-show="personSelected">
                                <button type="button" @click="backToPeople()" class="mb-3 inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                                    <x-icon name="chevron-left" class="h-4 w-4" /> <span x-text="personSelected?.name"></span>
                                </button>
                                <p x-show="!personPhotos.length" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('contacts.ui.avatar_no_images') }}</p>
                                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                    <template x-for="ph in personPhotos" :key="ph.id">
                                        <button type="button" @click="startCrop(ph.full)" class="aspect-square overflow-hidden rounded-md ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-900">
                                            <img :src="ph.thumb" alt="" class="h-full w-full object-cover" loading="lazy">
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Files --}}
                        <div x-show="avatarModal.tab==='files' && !avatarModal.loading">
                            <p x-show="!filePhotos.length" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('contacts.ui.avatar_no_images') }}</p>
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                <template x-for="(p,i) in filePhotos" :key="i">
                                    <button type="button" @click="startCrop(p.url)" class="aspect-square overflow-hidden rounded-md ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-900" :title="p.name">
                                        <img :src="p.url" alt="" class="h-full w-full object-cover" loading="lazy">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
