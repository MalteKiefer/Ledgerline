<?php

declare(strict_types=1);

return [
    'title' => 'Обзор',
    'subtitle' => 'Ваши треки и геотегированные фото на приватной карте.',

    // Views
    'view_media' => 'Медиа',
    'view_tracks' => 'Треки',

    // Locked / empty states
    'locked' => 'Разблокируйте хранилище, чтобы просмотреть карту.',
    'empty_tracks' => 'Треков пока нет. Импортируйте файл GPX, KML, KMZ, TCX или FIT для начала.',
    'empty_media' => 'Геотегированные фото не найдены. Импортируйте треки, чтобы автоматически привязать фото с временными метками.',
    'load_failed' => 'Не удалось загрузить карту.',
    'map_unavailable' => 'Карта недоступна (тайловый сервер не настроен).',

    // Import
    'import' => 'Импортировать трек',
    'importing' => 'Импорт…',
    'import_failed' => 'Ошибка импорта',
    'import_done' => 'Трек импортирован',
    'kmz_no_kml' => 'В архиве KMZ не найден KML-файл.',

    // Track list / stats
    'tracks_heading' => 'Треки',
    'distance' => 'Расстояние',
    'duration' => 'Продолжительность',
    'moving_time' => 'Время в движении',
    'ascent' => 'Набор высоты',
    'descent' => 'Потеря высоты',
    'max_speed' => 'Макс. скорость',
    'avg_speed' => 'Средняя скорость',
    'points' => 'Точки',
    'elevation' => 'Высота',
    'elevation_profile' => 'Профиль высот',
    'no_elevation' => 'Нет данных о высоте',
    'min_elevation' => 'Мин. высота',
    'max_elevation' => 'Макс. высота',
    'delete_track' => 'Удалить трек',
    'delete_track_confirm' => 'Удалить этот трек навсегда? Это действие нельзя отменить.',
    'rename_track' => 'Переименовать трек',
    'edit_name' => 'Изменить название',
    'cancel' => 'Отмена',
    'started' => 'Начало',
    'ended' => 'Конец',
    'note' => 'Заметка',
    'note_placeholder' => 'Добавить заметку…',
    'details' => 'Детали',
    'back' => 'Назад',
    'coupled_photos' => 'Фото на этом треке',

    // Search
    'search_tracks' => 'Поиск треков…',
    'search_media' => 'Поиск фото…',
    'no_search_results' => 'Ничего не найдено.',

    // Tour planning
    'plan_tour' => 'Запланировать маршрут',
    'planning_hint' => 'Нажмите на карту, чтобы добавить путевые точки.',
    'undo_point' => 'Отменить последнюю точку',
    'save_route' => 'Сохранить маршрут',
    'route_name' => 'Название маршрута',
    'planned_route_default' => 'Маршрут',
    'planned_route' => 'Запланированный маршрут',
    'auto_route' => 'Следовать дорогам / Авторасчёт',
    'auto_route_hint' => 'Привязывает путевые точки к реальным дорогам через сервис маршрутизации. Отключено — рисует прямые линии и работает полностью офлайн.',
    'auto_route_routing' => 'Расчёт маршрута…',
    'auto_route_fallback' => 'Не удалось получить маршрут — используются прямые линии.',
    'auto_route_rate_limited' => 'Маршрутизация временно ограничена — используются прямые линии. Попробуйте чуть позже.',
    'auto_route_too_many' => 'Слишком много путевых точек для авторасчёта — используются прямые линии.',

    // Live planning stats
    'plan_waypoints' => 'Путевые точки',
    'plan_distance' => 'Расстояние',
    'plan_duration' => 'Ожид. время',
    'surfaces' => 'Покрытие',
    'surface' => [
        'asphalt' => 'Асфальт',
        'paved' => 'Твёрдое',
        'unpaved' => 'Грунтовое',
        'gravel' => 'Гравий',
        'ground' => 'Грунт',
        'dirt' => 'Земля',
        'grass' => 'Трава',
        'sand' => 'Песок',
        'concrete' => 'Бетон',
        'cobblestone' => 'Булыжник',
        'paving_stones' => 'Брусчатка',
        'wood' => 'Дерево',
        'compacted' => 'Уплотнённое',
        'fine_gravel' => 'Мелкий гравий',
        'unknown' => 'Неизвестно',
        'other' => 'Другое',
    ],

    // Assign-to-tour modal source filter
    'filter_all' => 'Все',
    'filter_imported' => 'Импортированные',
    'filter_planned' => 'Запланированные',
    'filter_recorded' => 'Записанные',

    // Coupling
    'coupling' => 'Привязка фото',
    'match_photos' => 'Привязать фото к трекам',
    'matching' => 'Привязка…',
    'matched' => ':n фото привязано',
    'source_exif' => 'Из GPS фото',
    'source_interpolated' => 'Из трека (по времени)',
    'source_manual' => 'Установлено вручную',
    'source_none' => 'Без привязки',
    'clear_coupling' => 'Убрать привязку',
    'assign_to_track' => 'Привязать к треку',

    // Settings
    'settings' => 'Параметры привязки',
    'time_tolerance' => 'Допуск по времени (секунды)',
    'distance_tolerance' => 'Допуск по расстоянию (метры)',
    'settings_hint' => 'Насколько близко по времени и расстоянию фото должно быть, чтобы считаться частью трека.',
    'save' => 'Сохранить',
    'close' => 'Закрыть',

    // Units
    'unit_km' => 'km',
    'unit_m' => 'm',
    'unit_kmh' => 'km/h',
];
