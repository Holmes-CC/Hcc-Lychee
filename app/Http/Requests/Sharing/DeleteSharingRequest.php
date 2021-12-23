<?php

namespace App\Http\Requests\Sharing;

use App\Facades\AccessControl;
use App\Http\Requests\BaseApiRequest;
use App\Rules\IntegerIDListRule;

class DeleteSharingRequest extends BaseApiRequest
{
	public const SHARE_IDS_ATTRIBUTE = 'shareIDs';

	/**
	 * @var array<int>
	 */
	protected array $shareIDs = [];

	/**
	 * {@inheritDoc}
	 */
	public function authorize(): bool
	{
		return AccessControl::can_upload();
	}

	/**
	 * {@inheritDoc}
	 */
	public function rules(): array
	{
		return [
			self::SHARE_IDS_ATTRIBUTE => ['required', new IntegerIDListRule()],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function processValidatedValues(array $values, array $files): void
	{
		$this->shareIDs = explode(',', $values[self::SHARE_IDS_ATTRIBUTE]);
		array_walk($this->shareIDs, function (&$id) { $id = intval($id); });
	}

	/**
	 * @return array<int>
	 */
	public function shareIDs(): array
	{
		return $this->shareIDs;
	}
}
