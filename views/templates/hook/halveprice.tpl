{if isset($CATEGORY_REDUCTIONS)}
    {foreach from=$CATEGORY_REDUCTIONS item=reduction key=categoryId}
        <tr>
            <td>{$categoryId}</td>
            <td>{$reduction}</td>
        </tr>
    {/foreach}
{/if}
