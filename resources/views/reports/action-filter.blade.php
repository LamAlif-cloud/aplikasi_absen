<label>Filter Tindakan
<select name="action">@foreach(\App\Services\Discipline::FILTERS as $value=>$label)<option value="{{ $value }}" @selected($action===$value)>{{ $label }}</option>@endforeach</select>
</label>
