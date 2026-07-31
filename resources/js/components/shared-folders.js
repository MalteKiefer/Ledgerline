import { getJson, postForm, csrfToken } from '../shared/api';

// Plaintext cross-user folder sharing (pivot) — a self-contained Alpine island
// embedded in the Files page via files/_shared_partial.blade.php. Two panes:
//  - "Shared by me": the owner creates a share (folder + recipient email + role),
//    lists shares, changes a member's role, removes a member, deletes a share.
//  - "Shared with me": the member lists grants, browses the shared subtree,
//    downloads, and (as an editor) uploads / renames / deletes.
// All URLs are handed in from the blade cfg so this file never hardcodes routes.
export default function sharedFolders(cfg) {
    return {
        cfg,
        loading: true,
        error: '',

        // Owner pane.
        ownerShares: [],
        folders: [],
        form: { folderId: '', email: '', role: 'viewer' },
        creating: false,

        // Member pane.
        sharedWithMe: [],
        open: null, // the currently-browsed share summary
        browse: { role: 'viewer', root_id: null, folders: [], files: [] },
        busy: false,
        renameId: null,
        renameValue: '',

        async init() {
            await this.reload();
        },

        async reload() {
            this.loading = true;
            this.error = '';
            try {
                const [owner, mine, folders] = await Promise.all([
                    getJson(this.cfg.ownerIndex),
                    getJson(this.cfg.memberIndex),
                    getJson(this.cfg.foldersUrl),
                ]);
                this.ownerShares = owner.shares ?? [];
                this.sharedWithMe = mine.shares ?? [];
                this.folders = folders.folders ?? [];
            } catch {
                this.error = this.cfg.t.load_failed;
            } finally {
                this.loading = false;
            }
        },

        // ---- Owner actions ----

        async createShare() {
            if (! this.form.folderId || ! this.form.email.trim()) return;
            this.creating = true;
            this.error = '';
            try {
                const res = await fetch(this.cfg.ownerStore, {
                    method: 'POST',
                    headers: this._jsonHeaders(),
                    body: JSON.stringify({
                        file_folder_id: Number(this.form.folderId),
                        email: this.form.email.trim(),
                        role: this.form.role,
                    }),
                });
                if (res.status === 422) {
                    this.error = this.cfg.t.recipient_not_found;
                    return;
                }
                if (! res.ok) throw new Error('failed');
                this.form.email = '';
                await this.reload();
            } catch {
                this.error = this.cfg.t.save_failed;
            } finally {
                this.creating = false;
            }
        },

        async changeRole(share, member, role) {
            try {
                await postForm(this._url(this.cfg.ownerMember, share.id), { user_id: member.user_id, role }, 'PUT');
                await this.reload();
            } catch {
                this.error = this.cfg.t.save_failed;
            }
        },

        async removeMember(share, member) {
            try {
                await postForm(this._url(this.cfg.ownerMember, share.id), { user_id: member.user_id }, 'DELETE');
                await this.reload();
            } catch {
                this.error = this.cfg.t.save_failed;
            }
        },

        async deleteShare(share) {
            try {
                await postForm(this._url(this.cfg.ownerDestroy, share.id), null, 'DELETE');
                await this.reload();
            } catch {
                this.error = this.cfg.t.save_failed;
            }
        },

        // ---- Member actions ----

        async openShare(share) {
            this.error = '';
            try {
                const data = await getJson(this._url(this.cfg.memberBrowse, share.id));
                this.open = share;
                this.browse = data;
            } catch {
                this.error = this.cfg.t.load_failed;
            }
        },

        closeShare() {
            this.open = null;
            this.renameId = null;
        },

        canEdit() {
            return this.browse.role === 'editor' || this.browse.role === 'owner';
        },

        fileUrl(file, download = false) {
            const base = this._url(this.cfg.memberRaw, this.open.id).replace('__FILE__', String(file.id));
            return download ? base + '?download=1' : base;
        },

        async uploadFiles(event) {
            const files = event.target?.files;
            if (! files || ! files.length || ! this.open) return;
            this.busy = true;
            this.error = '';
            try {
                for (const f of files) {
                    const body = new FormData();
                    body.append('file', f);
                    if (this.browse.root_id != null) body.append('file_folder_id', String(this.browse.root_id));
                    const res = await fetch(this._url(this.cfg.memberUpload, this.open.id), {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                        body,
                    });
                    if (res.status === 413) { this.error = this.cfg.t.quota; break; }
                    if (! res.ok) throw new Error('failed');
                }
                await this.openShare(this.open);
            } catch {
                this.error = this.cfg.t.save_failed;
            } finally {
                this.busy = false;
                if (event.target) event.target.value = '';
            }
        },

        startRename(file) {
            this.renameId = file.id;
            this.renameValue = file.name;
        },

        async commitRename(file) {
            const name = this.renameValue.trim();
            if (! name) { this.renameId = null; return; }
            try {
                await postForm(this._fileUrl(this.cfg.memberRename, file.id), { name }, 'PUT');
                this.renameId = null;
                await this.openShare(this.open);
            } catch {
                this.error = this.cfg.t.save_failed;
            }
        },

        async deleteFile(file) {
            try {
                await postForm(this._fileUrl(this.cfg.memberDelete, file.id), null, 'DELETE');
                await this.openShare(this.open);
            } catch {
                this.error = this.cfg.t.save_failed;
            }
        },

        // ---- Helpers ----

        _jsonHeaders() {
            return { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() };
        },

        _url(template, shareId) {
            return template.replace('__SHARE__', String(shareId));
        },

        _fileUrl(template, fileId) {
            return this._url(template, this.open.id).replace('__FILE__', String(fileId));
        },

        formatBytes(n) {
            const b = Number(n) || 0;
            if (b < 1024) return b + ' B';
            const u = ['KB', 'MB', 'GB', 'TB'];
            let v = b / 1024, i = 0;
            while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
            return v.toFixed(1) + ' ' + u[i];
        },
    };
}
