{extends file='parent.tpl'}

{block title}child | {$smarty.block.parent}{/block}

{block main}{$smarty.block.parent}
This is child body.
this is {$value}
{$ex->getCode()}
{$array.3}
{foreach $array as $key => $value}
    {if $key === 2}
        {$value}
    {/if}
{/foreach}
{/block}
