/**
 * External dependencies
 */
import moment from 'moment';
import PropTypes from 'prop-types';

/**
 * Internal dependencies
 */
import TimelineItem from './timeline-item';

const TimelineGroup = ( props ) => {
	const { items, groupKey } = props;
	const dayString = moment( groupKey.toString() ).format( 'MMMM D, YYYY' );

	const dayToTimelineItem = ( item, itemIndex ) => {
		const itemKey = groupKey + '-' + itemIndex;
		return (
			<TimelineItem key={ itemKey } item={ item } itemKey={ itemKey } />
		);
	};

	const timelineItems = items.map( dayToTimelineItem );

	return (
		<li>
			{ dayString }
			<ul>{ timelineItems }</ul>
		</li>
	);
};

TimelineGroup.propTypes = {
	/**
	 * Additional CSS classes.
	 */
	className: PropTypes.string,
	/**
	 * An array of list items.
	 */
	items: PropTypes.arrayOf(
		PropTypes.shape( {
			/**
			 * Timestamp (in seconds) for the timeline item.
			 */
			datetime: PropTypes.number.isRequired,
			/**
			 * GridIcon for the Timeline item.
			 */
			gridicon: PropTypes.string.isRequired,
			/**
			 * Headline displayed for the list item.
			 */
			headline: PropTypes.string.isRequired,
			/**
			 * Body displayed for the list item.
			 */
			body: PropTypes.arrayOf( PropTypes.string ),
		} )
	).isRequired,
	groupKey: PropTypes.number.isRequired,
};

export default TimelineGroup;