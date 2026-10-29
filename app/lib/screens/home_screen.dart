import 'package:flutter/material.dart';

import '../state/session.dart';
import '../widgets/cards.dart';
import '../widgets/remote_list.dart';

class _Destination {
  const _Destination(this.label, this.icon, this.builder);

  final String label;
  final IconData icon;
  final Widget Function() builder;
}

/// Home screen. The tabs depend on the role; the server still enforces the role.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.session});

  final Session session;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _index = 0;
  late final List<_Destination> _destinations;

  @override
  void initState() {
    super.initState();
    _destinations = _destinationsFor(widget.session.user!.role);
  }

  List<_Destination> _destinationsFor(String role) {
    final api = widget.session.api;
    switch (role) {
      case 'approver':
        return [
          _Destination(
            'Pending approvals',
            Icons.pending_actions_outlined,
            () => RemoteList(
              api: api,
              path: '/requests',
              query: const {'status': 'PENDING'},
              emptyTitle: 'Nothing waiting',
              emptyMessage: 'No requests are waiting for approval.',
              itemBuilder: (c, item) => RequestCard(item, showBorrower: true),
            ),
          ),
          _Destination(
            'Overdue',
            Icons.alarm_outlined,
            () => RemoteList(
              api: api,
              path: '/custody',
              query: const {'overdue': '1'},
              emptyTitle: 'Nothing overdue',
              emptyMessage: 'No released items are past their due date.',
              itemBuilder: (c, item) => CustodyCard(item),
            ),
          ),
          _Destination(
            'Manual handling',
            Icons.support_agent_outlined,
            () => RemoteList(
              api: api,
              path: '/manual-handling',
              emptyTitle: 'No failed notifications',
              emptyMessage: 'Every notification was delivered or already handled.',
              itemBuilder: (c, item) => ManualHandlingCard(item),
            ),
          ),
        ];
      case 'staff':
        return [
          _Destination(
            'Approved items',
            Icons.inventory_2_outlined,
            () => RemoteList(
              api: api,
              path: '/requests',
              query: const {'status': 'APPROVED'},
              emptyTitle: 'Nothing to release',
              emptyMessage: 'No approved requests are waiting for release.',
              itemBuilder: (c, item) => RequestCard(item, showBorrower: true),
            ),
          ),
          _Destination(
            'Custody',
            Icons.assignment_ind_outlined,
            () => RemoteList(
              api: api,
              path: '/custody',
              emptyTitle: 'Nothing out right now',
              emptyMessage: 'No items are currently released to borrowers.',
              itemBuilder: (c, item) => CustodyCard(item),
            ),
          ),
        ];
      default:
        return [
          _Destination(
            'My requests',
            Icons.list_alt_outlined,
            () => RemoteList(
              api: api,
              path: '/requests',
              emptyTitle: 'No requests yet',
              emptyMessage: 'Requests you submit will show up here.',
              itemBuilder: (c, item) => RequestCard(item),
            ),
          ),
          _Destination(
            'Equipment',
            Icons.devices_other_outlined,
            () => RemoteList(
              api: api,
              path: '/equipment',
              emptyTitle: 'No equipment',
              emptyMessage: 'The equipment list is empty.',
              itemBuilder: (c, item) => EquipmentCard(item),
            ),
          ),
        ];
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.session.user!;
    final wide = MediaQuery.of(context).size.width >= 700;
    final current = _destinations[_index];
    // The key makes each tab load fresh data when you switch to it.
    final body = KeyedSubtree(key: ValueKey(_index), child: current.builder());

    return Scaffold(
      appBar: AppBar(
        title: Text(current.label),
        actions: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Center(child: Text('${user.name} (${user.role})')),
          ),
          IconButton(
            tooltip: 'Sign out',
            icon: const Icon(Icons.logout),
            onPressed: widget.session.logout,
          ),
        ],
      ),
      body: wide
          ? Row(
              children: [
                NavigationRail(
                  selectedIndex: _index,
                  labelType: NavigationRailLabelType.all,
                  onDestinationSelected: (i) => setState(() => _index = i),
                  destinations: [
                    for (final d in _destinations)
                      NavigationRailDestination(icon: Icon(d.icon), label: Text(d.label)),
                  ],
                ),
                const VerticalDivider(width: 1),
                Expanded(child: body),
              ],
            )
          : body,
      bottomNavigationBar: wide || _destinations.length < 2
          ? null
          : NavigationBar(
              selectedIndex: _index,
              onDestinationSelected: (i) => setState(() => _index = i),
              destinations: [
                for (final d in _destinations)
                  NavigationDestination(icon: Icon(d.icon), label: d.label),
              ],
            ),
    );
  }
}
