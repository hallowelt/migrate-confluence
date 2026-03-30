<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

/**
 * <ac:structured-macro ac:name="loremipsum">
 *  <ac:parameter ac:name="">2</ac:parameter>
 * </ac:structured-macro>
 *
 * See https://confluence.atlassian.com/conf59/loremipsum-macro-792499139.html
 */
class LoremIpsumMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'loremipsum';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'LoremIpsum';
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$paragraphs = 0;

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}

			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === '' ) {
					$paragraphs = (int)$childNode->nodeValue;
					break;
				}
			}
		}

		$loremIpsum = [];
		for ( $index = 0; $index < $paragraphs; $index++ ) {
			$loremIpsum[] = $this->getLoremIpsum( $index );
		}

		$replacement = '{{' . $this->getWikiTextTemplateName() . '###BREAK###' . "\n";
		$replacement .= '| paragraphs = ' . $paragraphs . '###BREAK###' . "\n";
		$replacement .= '| body = ###BREAK###' . "\n";
		$replacement .= implode( "\n\n", $loremIpsum );
		$replacement .= '}}';
		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode(
				$replacement
			),
			$node
		);
	}

	private function getLoremIpsum( int $paragraph ): string {
		$paragraphs = [
			0 => implode( ' ', [
				'Lorem ipsum dolor sit amet, consectetuer adipiscing elit.',
				'Aenean commodo ligula eget dolor. Aenean massa.',
				'Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.',
				'Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem.',
				'Nulla consequat massa quis enim.'
			] ),
			1 => implode( ' ', [
				'Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu.',
				'In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo.',
				'Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus.',
				'Vivamus elementum semper nisi. Aenean vulputate eleifend tellus.'
			] ),
			2 => implode( ' ', [
				'Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim.',
				'Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus.',
				'Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet.' .
				'Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus.'
			] ),
			3 => implode( ' ', [
				'Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero,',
				'sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar,',
				'hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut',
				'libero venenatis faucibus. Nullam quis ante.'
			] ),
			4 => implode( ' ', [
				'Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh.',
				'Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc,',
				'quis gravida magna mi a libero. Fusce vulputate eleifend sapien.',
				'Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus.'
			] ),
			5 => implode( ' ', [
				'Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla.',
				'Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae;',
				'In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor,',
				'suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris.'
			] ),
			6 => implode( ' ', [
				'Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing.',
				'Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero.',
				'Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet,',
				'nonummy id, imperdiet feugiat, pede. Sed lectus.'
			] ),
			7 => implode( ' ', [
				'Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis.',
				'Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non,',
				'auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae,',
				'posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa.',
				'Sed cursus turpis vitae tortor.'
			] ),
			8 => implode( ' ', [
				'Donec posuere vulputate arcu. Phasellus accumsan cursus velit.',
				'Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae;',
				'Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci.',
				'Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed,',
				'posuere ac, mattis non, nunc.'
			] ),
			9 => implode( ' ', [
				'Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere.',
				'Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc,',
				'eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a,',
				'suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum,',
				'sem libero volutpat nibh, nec pellentesque velit pede quis nunc.',
				'Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae;',
				'Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. '
			] ),
		];

		$index = $paragraph % 10;
		return $paragraphs[$index];
	}
}
