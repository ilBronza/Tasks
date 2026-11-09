<?php

namespace IlBronza\Tasks\Providers\DatatablesFields;

use IlBronza\Datatables\DatatablesFields\Editor\DatatableFieldText;

class DatatableFieldEditorText extends DatatableFieldText
{
	public function transformValue($task)
	{
		[$id, $text] = parent::transformValue($task);

		return [$id, e($text)];
	}
}
