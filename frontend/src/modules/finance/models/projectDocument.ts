import type { OffsetPage, PageQuery } from '@spa/modules/finance/models/project';

export type ProjectDocumentSourceType = 'finance_series' | 'legacy_invoice' | 'file' | 'gallery_photo' | 'finance_receipt' | 'bank_transaction' | 'bank_transaction_receipt';
export type ProjectDocumentRole = 'source_quote' | 'quote' | 'invoice' | 'payment' | 'receipt' | 'file' | 'photo' | 'other';
export type ProjectDocumentAvailability = 'available' | 'deleted' | 'missing';
export type ProjectDocumentState = 'active' | 'detached' | 'all';

export interface ProjectDocumentSource {
  source_type: ProjectDocumentSourceType;
  source_reference: string;
  pinned_revision_id: number | null;
}
export interface ProjectDocumentInput extends ProjectDocumentSource { role: ProjectDocumentRole }
export interface ProjectDocumentMetadata extends ProjectDocumentSource {
  title: string | null; mime: string | null; size: number | null; sha256: string | null; document_type: string | null;
  document_label: string | null; occurred_at: string | null; availability: ProjectDocumentAvailability; capability_url: string | null;
}
export type ProjectDocumentSnapshot = Partial<Omit<ProjectDocumentMetadata, 'availability' | 'capability_url'>>;
export interface ProjectDocument {
  link_id: number; project_id: string; source: ProjectDocumentSource; role: ProjectDocumentRole; snapshot: ProjectDocumentSnapshot;
  current: ProjectDocumentMetadata | null; availability: ProjectDocumentAvailability; attached_at: string; detached: boolean; detached_at: string | null;
}
export interface ProjectDocumentFilters extends PageQuery { state?: ProjectDocumentState }
export type ProjectDocumentPage = OffsetPage<ProjectDocument>;
export interface ProjectDocumentSourceFilters { cursor?: string | null; per_page?: number }
export interface ProjectDocumentSourcePage { data: ProjectDocumentMetadata[]; next_cursor: string | null }
