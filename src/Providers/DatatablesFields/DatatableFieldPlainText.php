<?php

namespace IlBronza\Tasks\Providers\DatatablesFields;

use IlBronza\Datatables\DatatablesFields\DatatableFieldText;

class DatatableFieldPlainText extends DatatableFieldText
{
	public function transformValue($value)
	{
		return e($value);
	}
}
