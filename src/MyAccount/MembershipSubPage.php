<?php

namespace Jankx\Extensions\MembershipLevels\MyAccount;

use Jankx\Extensions\MyAccount\SubPage\AbstractSubPage;

class MembershipSubPage extends AbstractSubPage
{
    public function getSlug(): string
    {
        return 'membership';
    }

    public function getLabel(): string
    {
        return __('Hội viên', 'jankx');
    }

    public function getIcon(): string
    {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/></svg>';
    }

    public function getPriority(): int
    {
        return 25;
    }

    public function getExtension(): ?string
    {
        return 'membership-levels';
    }

    public function getContent(): string
    {
        return '<!-- wp:jankx/account-tab-membership -->'
            . '<!-- wp:jankx/membership-current-tier /-->'
            . '<!-- wp:jankx/membership-privileges /-->'
            . '<!-- wp:jankx/membership-all-levels /-->'
            . '<!-- /wp:jankx/account-tab-membership -->';
    }
}