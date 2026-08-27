<?php

/**
 * CollectionFaqModel — câu hỏi thường gặp của MỘT bộ sưu tập.
 *
 * Bảng nhỏ nhất trong dự án và cố ý giữ nguyên như thế: một câu hỏi, một câu
 * trả lời, một thứ tự. Không trạng thái duyệt, không lượt xem, không "câu này
 * có hữu ích không" — mỗi cột thêm vào là một ô nữa trong form quản trị mà
 * không ai điền.
 *
 * Vì sao là BẢNG chứ không phải config: câu hỏi khác nhau theo bộ. "Kính râm
 * bộ này lắp được độ cận không" chỉ có nghĩa với một bộ toàn kính râm, và câu
 * trả lời nhắc đích danh mẫu nào lắp được tới bao nhiêu độ. Lý do đầy đủ ở
 * migrations/2026-08-27-bo-suu-tap-khung-ba-lop.sql.
 */

class CollectionFaqModel extends BaseModel
{
    protected static string $table = 'collection_faqs';

    /**
     * Câu hỏi của một bộ, theo thứ tự trưng bày.
     *
     * `created_at` chốt cuối để hai câu cùng sort_order vẫn ra thứ tự ổn định
     * giữa các lần tải — cùng lý do mà CollectionModel::ORDER phải có `name`.
     */
    public static function forCollection(string $collectionId): array
    {
        if ($collectionId === '') {
            return [];
        }

        return static::where(['collection_id' => $collectionId], 'sort_order ASC, created_at ASC');
    }
}
