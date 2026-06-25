# Displaying campus events with the UCSC Events block

The **UCSC Events** block shows upcoming events pulled live from the campus events calendar at [events.ucsc.edu](https://events.ucsc.edu). You pick one or more **organizers** (the departments, programs, or groups that host events), and the block displays their upcoming events automatically.

This guide walks through it in plain language. No coding required, and you don't need to find or build any web address — just search for the organizers by name.

---

## Part 1 — Add the block

1. Edit the WordPress page or post where you want the events to appear.
2. Click the **+** (block inserter) and search for **UCSC Events**.
3. Select it to drop the block onto the page.

When first placed, the block shows a placeholder prompting you to choose an organizer. Nothing is displayed until you select at least one.

## Part 2 — Choose organizers

1. With the block selected, open its settings panel on the right (the **Event Settings** panel). If you don't see it, click the gear/settings icon in the top-right of the editor.
2. Click the **Organizers** field and start typing the name of a department, program, or group — for example, `Humanities` or `Engineering`.
3. After you've typed a couple of letters, matching organizers appear in a dropdown. Click one to add it as a tag.
4. Repeat to add as many organizers as you like. The block shows the upcoming events from **all** of the organizers you've selected.
5. To remove an organizer, click the **×** on its tag.

A preview of the upcoming events appears in the editor after a moment.

> [!TIP]
> Not sure of an organizer's exact name? Type part of it — the search matches anywhere in the name. You can browse the full list of organizers at <https://events.ucsc.edu/organizers/>.

## Part 3 — Adjust how events are shown

In the same **Event Settings** panel you can fine-tune the display:

- **Number of Events** — how many upcoming events to show (1–40).
- **Layout Style** — **List**, **Grid**, or **Cards**.
- **Hide repeating events** — when on, a recurring event appears only once (its next upcoming date) instead of listing every occurrence.

Save or update the page, and the events will show on the live site.

---

## Troubleshooting

**The block just shows the "Select one or more organizers" placeholder.**
No organizers have been chosen yet. Open the **Event Settings** panel and add at least one organizer in the **Organizers** field (Part 2).

**The preview says "No upcoming events found for the selected organizers."**
The organizers were found, but none of them have events dated today or later. Past events are never shown. Try adding another organizer, or check back when that group has scheduled something new.

**I start typing but no organizers appear.**

- Type at least two letters — the search doesn't run on a single character.
- Give it a moment; the search waits until you pause typing before it looks.
- Double-check the spelling, or try a shorter, more general part of the name (for example, `Arts` instead of `Arts Division`).

**I selected an organizer but the events look out of date.**
The block caches events for a short time so pages load quickly, so changes can take a little while to appear. To refresh immediately, select the block and click the **Clear Cache** button (in the block toolbar or at the bottom of the settings panel).

**Where do these events come from?**
They are pulled live from the campus events calendar at [events.ucsc.edu](https://events.ucsc.edu). The block only displays events that are already published there — it doesn't create or edit events. To add or change an event, use the events calendar itself.
