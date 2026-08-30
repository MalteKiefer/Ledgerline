import type { OffsetPage, PageQuery } from '@spa/modules/finance/models/project';

export type HistorySourceKind = 'project_note' | 'document_note' | 'project_activity' | 'document_activity';
export type NoteType = 'note' | 'decision' | 'call' | 'email' | 'meeting' | 'correction';
export type NoteVisibility = 'internal' | 'customer';
export type SafeHistoryValue = string | number | boolean | null | SafeHistoryValue[] | { [key: string]: SafeHistoryValue };

export interface HistoryItem {
  id: number; source_kind: HistorySourceKind; type: string; visibility: NoteVisibility | null; body: string | null;
  supersedes_note_id: number | null; subject_type: string | null; subject_reference: string | null;
  payload: Record<string, SafeHistoryValue>; author_id: number | null; occurred_at: string; series_id: string | null; revision_id: number | null;
}
export interface NoteInput {
  revision_id?: number | null; type: NoteType; visibility: NoteVisibility; body: string; supersedes_note_id?: number | null;
}
export type HistoryPage = OffsetPage<HistoryItem>;
export interface HistoryCursorPage { data: HistoryItem[]; next_cursor: string | null }
export type HistoryPageQuery = PageQuery;
export interface HistoryCursorQuery { cursor?: string | null; per_page?: number }
