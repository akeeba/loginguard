<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die();

/** @var  FOF40\View\DataView\Html  $this */

?>

@extends('any:lib_fof40/Common/Browse')

@section('browse-filters')
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('name')
    </div>
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('username')
    </div>
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('email')
    </div>
    <div class="akeeba-filter-element akeeba-form-group">
        {{ \FOF40\Html\FEFHelper\BrowseView::genericSelect('group', \Akeeba\LoginGuard\Admin\Helper\Select::getGroupOptions(), $this->getModel()->getState('group', null, 'int'), ['list.none' => '&mdash; ' . JText::_('COM_LOGINGUARD_USER_FILTER_GROUP') . ' &mdash;', 'fof.autosubmit' => true]) }}
    </div>
    <div class="akeeba-filter-element akeeba-form-group">
        {{ \FOF40\Html\FEFHelper\BrowseView::publishedFilter('has2SV', 'COM_LOGINGUARD_USER_FIELD_HAS2SV') }}
    </div>
{{-- Filters above the table. --}}
@stop

@section('browse-table-header')
{{-- Table header. Column headers and optional filters displayed above the column headers. --}}
<tr>
    <th width="20px">
        @jhtml('FEFHelp.browse.checkall')
    </th>
    <th width="20px">
        @sortgrid('id', 'JGLOBAL_NUM')
    </th>
    <th>
        @sortgrid('name')
    </th>
    <th>
        @sortgrid('username')
    </th>
    <th>
        @sortgrid('email')
    </th>
    <th width="8%">
        @fieldtitle('has2SV')
    </th>
</tr>

@stop

@section('browse-table-body-norecords')
{{-- Table body shown when no records are present. --}}
<tr>
    <td colspan="99">
        @lang($this->getContainer()->componentName . '_COMMON_NORECORDS')
    </td>
</tr>
@stop

@section('browse-table-body-withrecords')
{{-- Table body shown when records are present. --}}
<?php $i = 0; ?>
<?php /** @var \Akeeba\LoginGuard\Admin\Model\Users $row */ ?>
@foreach($this->items as $row)
<?php $url = 'index.php?option=com_users&task=user.edit&id=' . (int)$row->id ?>
<tr>
    <td>
        @jhtml('FEFHelp.browse.id', ++$i, $row->getId())
    </td>
    <td>
        {{{ $row->getId() }}}
    </td>
    <td>
        <a href="{{ $url }}">
            {{{ $row->name }}}
        </a>
    </td>
    <td>
        <a href="{{ $url }}">
            {{{ $row->username }}}
        </a>
    </td>
    <td>
        <a href="{{ $url }}">
            {{{ $row->email }}}
        </a>
    </td>
    <td>
        @jhtml('FEFHelp.browse.published', $row->has2SV, $i, '', false)
    </td>
</tr>
@endforeach
@stop
