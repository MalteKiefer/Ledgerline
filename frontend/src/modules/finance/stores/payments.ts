import { defineStore } from 'pinia';
import { ref } from 'vue';
import { paymentApi, paymentErrorCode, type PaymentErrorCode } from '@spa/modules/finance/api/payments';
import type {
  AllocationLineInput,
  AllocationResult,
  Payment,
  PaymentListFilters,
  PaymentPage,
  PaymentSuggestions,
  RecordPaymentInput,
} from '@spa/modules/finance/models/payment';

type StoreError = PaymentErrorCode | 'request_failed';

const emptyPage = (): PaymentPage => ({
  data: [],
  links: { first: '', last: '', prev: null, next: null },
  meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
});

export const usePaymentsStore = defineStore('finance-v2-payments', () => {
  const items = ref<Payment[]>([]);
  const page = ref<PaymentPage>(emptyPage());
  const current = ref<Payment | null>(null);
  const suggestions = ref<PaymentSuggestions | null>(null);
  const listLoading = ref(false);
  const detailLoading = ref(false);
  const actionLoading = ref(false);
  const listError = ref<StoreError | null>(null);
  const detailError = ref<StoreError | null>(null);
  const actionError = ref<StoreError | null>(null);

  let listController: AbortController | null = null;
  let detailController: AbortController | null = null;
  let listSequence = 0;
  let detailSequence = 0;

  function failure(error: unknown): StoreError {
    return paymentErrorCode(error) ?? 'request_failed';
  }

  function upsert(payment: Payment): void {
    const index = items.value.findIndex(({ id }) => id === payment.id);
    if (index === -1) items.value = [payment, ...items.value];
    else items.value = items.value.map((item, offset) => offset === index ? payment : item);
    page.value = { ...page.value, data: items.value };
  }

  function select(payment: Payment): Payment {
    current.value = payment;
    upsert(payment);

    return payment;
  }

  async function loadList(filters: PaymentListFilters): Promise<void> {
    listController?.abort();
    const controller = new AbortController();
    listController = controller;
    const sequence = ++listSequence;
    listLoading.value = true;
    listError.value = null;
    try {
      const result = await paymentApi.list(filters, controller.signal);
      if (sequence !== listSequence) return;
      page.value = result;
      items.value = result.data;
    } catch (error) {
      if (sequence !== listSequence || isAbort(error)) return;
      listError.value = failure(error);
      throw error;
    } finally {
      if (sequence === listSequence) listLoading.value = false;
    }
  }

  async function loadPayment(id: string): Promise<Payment> {
    detailController?.abort();
    const controller = new AbortController();
    detailController = controller;
    const sequence = ++detailSequence;
    detailLoading.value = true;
    detailError.value = null;
    suggestions.value = null;
    try {
      const payment = await paymentApi.show(id, controller.signal);
      if (sequence === detailSequence) select(payment);

      return payment;
    } catch (error) {
      if (sequence === detailSequence && ! isAbort(error)) detailError.value = failure(error);
      throw error;
    } finally {
      if (sequence === detailSequence) detailLoading.value = false;
    }
  }

  async function loadSuggestions(id: string): Promise<PaymentSuggestions> {
    const result = await paymentApi.suggestions(id);
    suggestions.value = result;

    return result;
  }

  async function act<T>(operation: () => Promise<T>): Promise<T> {
    actionLoading.value = true;
    actionError.value = null;
    try {
      return await operation();
    } catch (error) {
      actionError.value = failure(error);
      throw error;
    } finally {
      actionLoading.value = false;
    }
  }

  function record(input: RecordPaymentInput, key: string): Promise<Payment> {
    return act(async () => select(await paymentApi.record(input, key)));
  }

  function allocate(id: string, lines: AllocationLineInput[], expectedVersion: number | null, key: string): Promise<AllocationResult> {
    return act(async () => {
      const result = await paymentApi.allocate(id, lines, expectedVersion, key);
      select(result.payment);

      return result;
    });
  }

  function reverse(allocationId: number, expectedPaymentVersion: number | null, key: string): Promise<AllocationResult> {
    return act(async () => {
      const result = await paymentApi.reverse(allocationId, expectedPaymentVersion, key);
      select(result.payment);

      return result;
    });
  }

  return {
    items, page, current, suggestions,
    listLoading, detailLoading, actionLoading,
    listError, detailError, actionError,
    loadList, loadPayment, loadSuggestions,
    record, allocate, reverse,
  };
});

function isAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}
