<div {if $type == 'content'} class="block-container"{else} class="block-holder" data-id="{$id}"{if $block && $block->byRef} data-byRef="{$block->byRef->id}"{/if}{/if}>

    {if $type !== 'content'}
        <div class="block-info">
            {if $moudleName}{$moudleName} :{else}Static{/if} {$renderTime}
            <div class="block-holder-actionbar">
                <a class="delete" href="#" title="{_('Delete block', 'FRAYM_ADMIN_CONTEXT_MENU_DELETE_BLOCK')}"><i class="fa fa-trash-o"></i></a>
                <a class="cut" href="#" title="{_('Cut block', 'FRAYM_ADMIN_CONTEXT_MENU_CUT_BLOCK')}"><i class="fa fa-cut"></i></a>
                <a class="copy" href="#" title="{_('Copy block', 'FRAYM_ADMIN_CONTEXT_MENU_COPY_BLOCK')}"><i class="fa fa-copy"></i></a>
                <a class="edit" href="#" title="{_('Edit block', 'FRAYM_ADMIN_CONTEXT_MENU_EDIT_BLOCK')}"><i class="fa fa-pencil"></i></a>
            </div>
        </div>
    {/if}

    <div class="block-container-content">
    {{$content}}
    </div>
</div>