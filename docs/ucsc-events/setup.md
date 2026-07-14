# Displaying campus events with the UCSC Events block

The **UCSC Events** block shows upcoming events pulled live from the campus events calendar at [events.ucsc.edu](https://events.ucsc.edu). You choose what to show — by **organizer** (the departments, programs, or groups that host events), by **category** or **tag**, or a combination — and the block displays the matching upcoming events automatically.

This guide walks through it in plain language. No coding required, and you don't need to find or build any web address — just search by name.

---

## Part 1 — Add the block

1. Edit the WordPress page or post where you want the events to appear.
2. Click the **+** (block inserter) and search for **UCSC Events**.
3. Select it to drop the block onto the page.

When first placed, the block shows a placeholder prompting you to choose an organizer, category, or tag. Nothing is displayed until you select at least one of them.

## Part 2 — Choose organizers

1. With the block selected, open its settings panel on the right (the **Event Settings** panel). If you don't see it, click the gear/settings icon in the top-right of the editor.
2. Click the **Organizers** field and start typing the name of a department, program, or group — for example, `Humanities` or `Engineering`.
3. After you've typed a couple of letters, matching organizers appear in a dropdown. Click one to add it as a tag.
4. Repeat to add as many organizers as you like. The block shows the upcoming events from **all** of the organizers you've selected.
5. To remove an organizer, click the **×** on its tag.

A preview of the upcoming events appears in the editor after a moment.

Organizers are optional. To show events by topic across the whole campus instead, skip this step and use the **category** or **tag** filters in Part 3.

> [!TIP]
> Not sure of an organizer's exact name? Type part of it — the search matches anywhere in the name. You can browse the full list of organizers at <https://events.ucsc.edu/organizers/>.

## Part 3 — Filter by category or tag (optional)

You can narrow events by topic using the **Filter by Category** and **Filter by Tag** fields in the **Event Settings** panel.

- **Filter by Category** — click the field to see the available event categories, then click one to add it. Only events in the selected categories are shown.
- **Filter by Tag** — start typing a tag name; matching tags appear after a couple of letters. Click one to add it.

You can combine these with organizers or use them on their own:

- **With organizers selected**, the filters narrow the results to those organizers' events in the chosen categories/tags.
- **With no organizer selected**, the block shows matching events from across the whole campus calendar.

Selecting more than one category (or more than one tag) broadens the results — events matching *any* of them are shown. Adding a category *and* a tag narrows the results to events that match both.

## Part 4 — Adjust how events are shown

In the same **Event Settings** panel you can fine-tune the display:

- **Number of Events** — how many upcoming events to show (1–40).
- **Layout Style** — **List**, **Grid**, or **Cards**.
- **Hide repeating events** — when on, a recurring event appears only once (its next upcoming date) instead of listing every occurrence.

Save or update the page, and the events will show on the live site.

---

## Troubleshooting

**The block just shows the "Select one or more organizers, categories, or tags" placeholder.**
Nothing has been chosen yet. Open the **Event Settings** panel and add at least one organizer, category, or tag (Parts 2–3).

**The preview says "No upcoming events found for the selected filters."**
Your selection was found, but nothing matching it has events dated today or later. Past events are never shown. If you've combined an organizer with a category or tag, the combination may be too narrow — try removing one of them, or check back when something new is scheduled.

**I start typing but no organizers appear.**

- Type at least two letters — the search doesn't run on a single character.
- Give it a moment; the search waits until you pause typing before it looks.
- Double-check the spelling, or try a shorter, more general part of the name (for example, `Arts` instead of `Arts Division`).

**I can't find a category or tag.**

- For **categories**, click the field to open the list of available categories — you don't have to type.
- For **tags**, type at least two letters and pause; matching tags appear once the search runs.
- Only categories and tags that already exist on the campus calendar can be selected.

**I selected an organizer but the events look out of date.**
The block caches events for a short time so pages load quickly, so changes can take a little while to appear. To refresh immediately, select the block and click the **Clear Cache** button (in the block toolbar or at the bottom of the settings panel).

**Where do these events come from?**
They are pulled live from the campus events calendar at [events.ucsc.edu](https://events.ucsc.edu). The block only displays events that are already published there — it doesn't create or edit events. To add or change an event, use the events calendar itself.
