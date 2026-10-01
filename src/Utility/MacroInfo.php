<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * info properties for macro support reporting
 */
class MacroInfo {

	/**
	 * support all features, parameters, behaviour
	 */
	public const SUPPORT_LEVEL_FULLY = 100;

	/**
	 * support all features apart from some edge cases
	 */
	public const SUPPORT_LEVEL_ALMOST_FULLY = 90;

	/**
	 * support basic features, but some functionality is missing
	 */
	public const SUPPORT_LEVEL_PARTIALLY = 50;

	/**
	 * support some kind of minimal set, but features will be lost
	 */
	public const SUPPORT_LEVEL_SKETCHY = 10;

	/**
	 * do not support this feature
	 */
	public const SUPPORT_LEVEL_NONE = 0;

	/**
	 * DO NOT USE THIS CONSTANT! It is only for reporting. Choose
	 * the appropriate level above.
	 */
	public const SUPPORT_LEVEL_UNKNOWN = -1;

}
