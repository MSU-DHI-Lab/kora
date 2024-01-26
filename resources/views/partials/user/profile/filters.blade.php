<section class="filters">
    <div class="pagination-options pagination-options-js">
        <select class="page-count option-dropdown-js" id="page-count-dropdown">
             <option value="10">10 per page</option>
                          <option value="20" {{app('request')->input('page-count') === '20' ? 'selected' : ''}}>20 per page</option>
                          <option value="30" {{app('request')->input('page-count') === '30' ? 'selected' : ''}}>30 per page</option>
                          <option value="50" {{app('request')->input('page-count') === '50' ? 'selected' : ''}}>50 per page</option>
                          <option value="100" {{app('request')->input('page-count') === '100' ? 'selected' : ''}}>100 per page</option>
                          <option value="500" {{app('request')->input('page-count') === '500' ? 'selected' : ''}}>500 per page</option>
                          <option value="1000" {{app('request')->input('page-count') === '1000' ? 'selected' : ''}}>1000 per page</option>
        </select>
        <select class="order option-dropdown-js" id="order-dropdown">
            <option value="lmd">Last Modified Descending</option>
            <option value="lma" {{app('request')->input('mcr-order') === 'lma' ? 'selected' : ''}}>Last Modified Ascending</option>
            <option value="idd" {{app('request')->input('mcr-order') === 'idd' ? 'selected' : ''}}>ID Descending</option>
            <option value="ida" {{app('request')->input('mcr-order') === 'ida' ? 'selected' : ''}}>ID Ascending</option>
        </select>
    </div>
    <div class="show-options show-options-js">
        <a href="#" class="expand-fields expand-fields-js tooltip" title="Expand all fields" tooltip="Expand All Records"><i class="icon icon-expand icon-expand-js"></i></a>
        <a href="#" class="collapse-fields collapse-fields-js tooltip" title="Collapse all fields" tooltip="Collapse All Records"><i class="icon icon-condense icon-condense-js"></i></a>
    </div>
</section>
