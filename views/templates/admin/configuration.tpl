{if isset($CATEGORY_REDUCTIONS)}
    {assign var='reductions' value=$CATEGORY_REDUCTIONS|@explode:',':}
    <table>
        <tr>
            <th>Category ID</th>
            <th>Percentage Reduction</th>
        </tr>
        {foreach from=$reductions item=reduction}
            {assign var='parts' value=$reduction|@explode=':'}
            <tr>
                <td>{$parts[0]}</td>
                <td>{$parts[1]}</td>
            </tr>
        {/foreach}
    </table>
{/if}
