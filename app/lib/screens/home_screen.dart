import 'package:flutter/material.dart';

import '../state/session.dart';
import '../widgets/cards.dart';
import '../widgets/dialogs.dart';
import '../widgets/remote_list.dart';
import 'new_request_screen.dart';
import 'request_detail_screen.dart';

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
  int _version = 0; // bumped after an action so the visible list reloads
  late final List<_Destination> _destinations;

  @override
  void initState() {
    super.initState();
    _destinations = _destinationsFor(widget.session.user!.role);
  }

  void _changed() {
    if (mounted) setState(() => _version++);
  }

  void _openDetail(dynamic id) {
    final requestId = id is num ? id.toInt() : int.tryParse('$id');
    if (requestId == null) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => RequestDetailScreen(
          api: widget.session.api,
          role: widget.session.user!.role,
          requestId: requestId,
          onChanged: _changed,
        ),
      ),
    );
  }

  void _newRequest() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => NewRequestScreen(api: widget.session.api, onCreated: _changed),
      ),
    );
  }

  Future<void> _resolve(Map<String, dynamic> item) async {
    final note = await askNote(
      context,
      title: 'Mark as handled',
      message: 'Write what you did, for example "Called the borrower in person".',
      required: true,
      confirmLabel: 'Mark handled',
    );
    if (note == null || !mounted) return;
    try {
      await widget.session.api.post(
        '/manual-handling/${item['id']}/resolve',
        body: {'note': note},
      );
      if (!mounted) return;
      showInfo(context, 'Marked as handled.');
      setState(() => _version++);
    } catch (e) {
      if (!mounted) return;
      showError(context, e);
    }
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
              itemBuilder: (c, item) => RequestCard(
                item,
                showBorrower: true,
                onTap: () => _openDetail(item['id']),
              ),
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
              itemBuilder: (c, item) => CustodyCard(
                item,
                onTap: () => _openDetail(item['request_id']),
              ),
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
              itemBuilder: (c, item) => ManualHandlingCard(
                item,
                onResolve: () => _resolve(item),
              ),
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
              itemBuilder: (c, item) => RequestCard(
                item,
                showBorrower: true,
                onTap: () => _openDetail(item['id']),
              ),
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
              itemBuilder: (c, item) => CustodyCard(
                item,
                onTap: () => _openDetail(item['request_id']),
              ),
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
              itemBuilder: (c, item) => RequestCard(
                item,
                onTap: () => _openDetail(item['id']),
              ),
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
    final body = KeyedSubtree(key: ValueKey('$_index-$_version'), child: current.builder());

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
      floatingActionButton: (user.role == 'borrower' && _index == 0)
          ? FloatingActionButton.extended(
              onPressed: _newRequest,
              icon: const Icon(Icons.add),
              label: const Text('New request'),
            )
          : null,
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
