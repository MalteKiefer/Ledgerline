<?php

declare(strict_types=1);

return [
    'title' => 'Счета',
    'new' => 'Новый счёт',
    'search' => 'Поиск счетов',
    'filter_all' => 'Все',
    'status_draft' => 'Черновик',
    'status_sent' => 'Отправлен',
    'status_paid' => 'Оплачен',

    'empty_title' => 'Счетов пока нет',
    'empty_hint' => 'Создайте первый счёт, чтобы начать.',
    'save_failed' => 'Не удалось загрузить счета.',

    'col_number' => 'Номер',
    'col_customer' => 'Клиент',
    'col_date' => 'Дата',
    'col_total' => 'Сумма',
    'col_status' => 'Статус',
    'col_actions' => 'Действия',
    'draft_label' => 'Черновик (без номера)',

    'back' => 'Назад',
    'customer' => 'Клиент',
    'choose_customer' => 'Выбрать из контактов',
    'no_customer' => 'Клиент не выбран',
    'clear_customer' => 'Очистить',
    'customer_name' => 'Имя',
    'customer_address' => 'Адрес',
    'customer_email' => 'Email',
    'customer_vat' => 'VAT ID',

    'issue_date' => 'Дата выставления',
    'due_date' => 'Срок оплаты',

    'lines' => 'Позиции',
    'line_desc' => 'Описание',
    'line_qty' => 'Кол-во',
    'line_unit' => 'Ед. изм.',
    'line_price' => 'Цена за единицу',
    'line_vat' => 'VAT %',
    'add_line' => 'Добавить позицию',
    'remove' => 'Удалить',
    'csv_import' => 'Импортировать CSV',
    'csv_hint' => 'Clockify detailed report — fills Start Date, Description and hours.',
    'csv_imported' => 'Импортировано позиций: :n.',
    'csv_bad_format' => 'Не удалось прочитать CSV (необходимы столбцы Description и Duration (decimal)).',

    'net' => 'Без НДС',
    'vat' => 'VAT',
    'vat_at' => 'VAT :rate%',
    'gross' => 'Итого',

    'note' => 'Заметка',
    'footer' => 'Нижний колонтитул',

    'finalize' => 'Завершить и присвоить номер',
    'mark_sent' => 'Отметить как отправленный',
    'mark_paid' => 'Отметить как оплаченный',
    'print' => 'Печать / PDF',
    'trash' => 'Переместить в корзину',
    'restore' => 'Восстановить',
    'delete' => 'Удалить безвозвратно',
    'delete_confirm' => 'Удалить этот счёт безвозвратно?',

    'company_missing' => 'Настройте профиль компании в параметрах, чтобы нумеровать и оформлять счета.',

    'picker_title' => 'Выбрать клиента',
    'picker_search' => 'Поиск контактов',
    'picker_empty' => 'Контакты не найдены.',

    'language' => 'Язык',
    'currency' => 'Валюта',
    'attn' => 'Контактное лицо',

    // Print / PDF sheet
    'print_title' => 'Счёт',
    'invoice_from' => 'От',
    'status_label' => 'Статус',
    'bill_to' => 'Получатель',
    'invoice_number' => 'Номер счёта',
    'invoice_date' => 'Дата',
    'due' => 'Срок',
    'amount' => 'Сумма',
    'subtotal' => 'Подытог',
    'tax_heading' => 'Налог',
    'taxable' => 'Налогооблагаемая база',
    'tax_amount' => 'Сумма налога',
    'notes_heading' => 'Примечания',
    'payment_terms_heading' => 'Условия оплаты',
    'payment_methods_heading' => 'Способы оплаты',
    'bank_details' => 'Банковские реквизиты',
    'vat_id_label' => 'VAT ID',
];
