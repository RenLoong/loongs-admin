<?php

declare(strict_types=1);

namespace App\Admin\Pages\Message;

use App\Admin\Enums\MessageLevel;
use Loongs\Render\AsPage;
use Loongs\Render\Builder\Detail;
use Loongs\Render\Component\Action;
use Loongs\Render\Component\Block;
use Loongs\Render\Component\Column;
use Loongs\Render\DetailPage;
use Loongs\Render\PageContext;

/** 消息详情 (hidden page node, P5). Opening does not mark the message read: 标为已读 does. */
#[AsPage('admin.message.detail', permission: 'message:list')]
final class MessageDetailPage extends DetailPage
{
    public function build(PageContext $ctx): Detail
    {
        return Detail::make('消息详情')
            ->breadcrumb(['消息', '消息详情'])
            ->load('/admin/api/messages/{id}')
            ->header('{title}', [
                Column::tag('level', '级别')->enum(MessageLevel::class),
            ], [
                Action::request('标为已读', 'PUT', '/admin/api/messages/{id}/read')->then('refresh')->primary()->hiddenWhen('read', 'eq', true),
            ])
            ->blocks([
                Block::descriptions([
                    Column::text('sender_name', '发送人'),
                    Column::tag('level', '级别')->enum(MessageLevel::class),
                    Column::datetime('created_at', '发送时间')->format('yyyy-MM-dd HH:mm'),
                    Column::datetime('read_at', '阅读时间')->format('yyyy-MM-dd HH:mm')->emptyText('未读'),
                ], 2),
                Block::markdown('{content}', '内容'),
            ]);
    }
}
